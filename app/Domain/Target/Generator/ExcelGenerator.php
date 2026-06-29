<?php

namespace App\Domain\Target\Generator;

use App\Domain\Source\CSVSource;
use App\Foundation\File\ICompress;
use App\Domain\Target\ExcelTarget;
use App\Domain\Target\Template\Excel\Tpl;
use App\Domain\Target\Template\Excel\ColHead;
use App\Domain\Target\Template\Excel\Node;
use App\Domain\Target\Template\Excel\RowHead;
use App\Domain\Target\Template\Excel\Style;
use App\Domain\Source\ISource;
use App\Exceptions\FileException;
use App\Exceptions\TargetException;
use EasySwoole\EasySwoole\Config;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use WecarSwoole\Util\File;
use SplQueue;
use App\ErrCode;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Excel 文件生成器
 * 根据源文件的行数和大小决定生成几个目标文件
 * 如果生成多个目标文件（或者单个文件达到一定尺寸），则执行归档压缩
 * 注意：多表格模式（一页显示多个表格，或者多个 tab）不会分文件
 */
class ExcelGenerator
{
    /**
     * 列标题信息：[列key, 列类型, 行索引位置]
     */
    private $colInfo = [];

    public function generate(ISource $source, ExcelTarget $target, ICompress $compress = null)
    {
        if (!$source instanceof CSVSource) {
            throw new \Exception("generate csv error:need CSVSource type", ErrCode::SOURCE_TYPE_ERR);
        }

        if (!file_exists($source->fileName())) {
            throw new FileException("源文件不存在：{$source->fileName()}", ErrCode::FILE_OP_FAILED);
        }

        // 计算需要生成多少个文件，每个文件最大多少行，并算出每个文件名称
        // 注意：只有提供了压缩器的情况下才有可能分文件，没有压缩器则不分割
        list($fileCount, $fileRowCount) = !$compress ? [1, PHP_INT_MAX] : $this->calcFileCount($source, $target);
        $fileNames = $this->calcFileNames($target->targetFileName(), $fileCount);

        if (!$sourceFile = @fopen($source->fileName(), 'rb')) {
            throw new FileException("打开源文件失败：{$source->fileName()}", ErrCode::FILE_OP_FAILED);
        }

        try {
            $this->extractColInfo($sourceFile);

            foreach ($fileNames as $index => $fileName) {
                // 生成 excel。最后一个文件的 row 不做限制
                $maxRow = $index < count($fileNames) - 1 ? $fileRowCount : PHP_INT_MAX;
                $this->createExcel($sourceFile, $fileName, $maxRow, $target);
            }
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        } finally {
            fclose($sourceFile);
            // 删除源文件
            unlink($source->fileName());
        }

        // 压缩
        if ($compress && count($fileNames) > 1 || $source->size() > Config::getInstance()->getConf("zip_threshold")) {
            $newTargetFileName = $compress->compress(File::join($target->getBaseDir(), 'target'), $fileNames);
            // 重新设置目标文件名字
            $target->setTargetFileName($newTargetFileName);
        }
    }

    private function extractFieldsAndTypes(array $csvFieldsArr): array
    {
        $fields = $types = [];
        foreach ($csvFieldsArr as $ft) {
            $val = explode('|', $ft);
            $fields[] = $val[0];
            $types[] = $val[1] ?? 'string';
        }

        return [$fields, $types];
    }

    private function extractColInfo($sourceFile)
    {
        if (!$fieldsAndTypes = fgetcsv($sourceFile)) {
            return;
        }

        list($colTitles, $colTypes) = $this->extractFieldsAndTypes($fieldsAndTypes);
        $rowHeadIndex = array_search(CSVSource::EXT_FIELD, $colTitles);// 行标题索引位置（针对有行表头的）

        $this->colInfo = [$colTitles, $colTypes, $rowHeadIndex];
    }

    /**
     * 生成 Excel 中的表格
     * @return array [row_offset, col_count]：行偏移值、本表格的列数
     */
    private function createTable(
        Worksheet $activeSheet,
        int $rowOffset,
        $sourceFile,
        int $maxRow,
        Tpl $tpl,
        string $title,
        string $summary,
        array $header,
        array $footer,
        string $headerAlign,
        string $footerAlign,
        int $rowHeight,
        int $colWidth
    ) {
        $startRowOffset = $rowOffset + 1;
        // 生成模板
        list($rowOffset, $colOffset, $rowMap, $colMap) = $this->createSheetTpl(
            $activeSheet,
            $tpl,
            $title,
            $summary,
            $header,
            $headerAlign,
            $rowOffset,
            $colWidth
        );
        // 行标题内部偏移值
        $rowHeadUsed = [];
        list($colTitles, $colTypes, $rowHeadIndex) = $this->colInfo;

        // 拿到所有列的 Style
        $allCols = Node::fetchAllLeaves($tpl->colHead());
        $colStyles = [];
        foreach ($allCols as $colNode) {
            $colStyles[$colNode->name()] = $colNode->style();
        }

        // 循环读取源数据写入到 excel 中
        while ($maxRow-- && !feof($sourceFile)) {
            if (!$rowValues = fgetcsv($sourceFile)) {
                continue;
            }

            if ($rowValues[0] === CSVSource::SPLIT_LINE) {
                // 遇到多源分割线，获取下一个表格的列信息后跳出
                $this->extractColInfo($sourceFile);
                 break;
            }

            $rowOffset++;

            /**
             * 填充一行数据
             */
            // 确定行号
            $theRowNum = $rowOffset;
            // 如果有 rowMap，则使用 rowMap 的行号
            if ($rowMap && $rowHeadIndex !== false) {
                $theRowName = $rowValues[$rowHeadIndex];
                if (isset($rowMap[$theRowName])) {
                    $innerIndex = isset($rowHeadUsed[$theRowName]) ? $rowHeadUsed[$theRowName] + 1 : 0;
                    if (!$theRowNum = $rowMap[$theRowName][$innerIndex] ?? 0) {
                        continue;
                    }

                    $rowHeadUsed[$theRowName] = isset($rowHeadUsed[$theRowName]) ? $rowHeadUsed[$theRowName] + 1 : 0;
                }
            }
            // 遍历每列值填充到 excel 中
            // 注意：源文件中的数据（rowValues）列数不一定和模板的一致，需要忽略掉多出来的部分
            foreach ($rowValues as $index => $val) {
                // 确定列号
                if (!isset($colTitles[$index]) || !$theColNum = ($colMap[$colTitles[$index]] ?? 0)) {
                    continue;
                }

                $cell = $activeSheet->getCell(Coordinate::stringFromColumnIndex($theColNum) . $theRowNum);
                
                // 单元格类型
                // 注意：在生成源 CSV 时，我们根据第一行数据探测了列类型，但这里仍然需要重新探测，防止同一列数据在不同行类型不一致
                $cellType = $colTypes[$index] == 'number' ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING;
                if (is_string($val) && !is_numeric($val)) {
                    $cellType = DataType::TYPE_STRING;
                }
                $cell->setValueExplicit($val, $cellType);

                // 单元格样式
                // 目前仅支持设置 align，后面有其他样式需求再加
                $style = $colStyles[$colTitles[$index]];
                if ($colAlign = $style->getAlign()) {
                    $cell->getStyle()->getAlignment()->setWrapText(true)->setHorizontal($colAlign)->setVertical(Alignment::VERTICAL_CENTER);
                }
            }

            // 设置行高度（使用默认行高度无效）
            $activeSheet->getRowDimension($theRowNum)->setRowHeight($rowHeight);
        }

        // 将 colOffset 偏移到结束位置
        $colOffset += count($colMap);

        // 设置整个表格边框（标题行不设置边框）
        $activeSheet->getStyle("A" . ($title ? $startRowOffset + 1 : $startRowOffset) . ':' . Coordinate::stringFromColumnIndex($colOffset) . $rowOffset)
        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // 设置页脚
        if ($footer) {
            $this->setFooter($activeSheet, $footer, $colOffset, $rowOffset, $footerAlign);
        }

        return [++$rowOffset, $colOffset];
    }

    /**
     * 生成 excel 文件
     * 一个 excel 中可能会生成多个 table
     * @param resource $sourceFile 源数据文件，资源对象
     * @param string $targetFileName 目标文件名
     * @param int $maxRow 最大读取行数
     * @param ExcelTarget $target 目标对象
     */
    private function createExcel($sourceFile, string $targetFileName, int $maxRow, ExcelTarget $target)
    {
        $spreadSheet = new Spreadsheet();
        $activeSheet = $spreadSheet->getActiveSheet();
        $this->setDefaultStyle($spreadSheet, $target);

        $rowOffset = $target->rowOffset() ?? 0;
        $tableIndex = 0;
        $maxColCount = 0;// 最大列数
        while (!feof($sourceFile) && $target->getTpls($tableIndex) && $rowOffset < $maxRow) {
            // 每次循环生成一个 table
            list($rowOffset, $colCount) = $this->createTable(
                $activeSheet,
                $rowOffset,
                $sourceFile,
                $maxRow,
                $target->getTpls($tableIndex),
                $target->getTitles($tableIndex),
                $target->getSummaries($tableIndex),
                $target->getHeaders($tableIndex),
                $target->getFooters($tableIndex),
                $target->getHeadersAlign($tableIndex),
                $target->getFootersAlign($tableIndex),
                $target->getDefaultHeight(),
                $target->getDefaultWidth()
            );

            // 每生成一个 table，将行号往下推移 3 行
            $rowOffset += 3;
            $tableIndex++;

            $maxColCount = max($maxColCount, $colCount);
        }

        // 设置打印区域
        $this->setPrint($activeSheet, $rowOffset - 3, $maxColCount, $tableIndex);

        $writer = new Xlsx($spreadSheet);
        $writer->save($targetFileName);

        $spreadSheet->disconnectWorksheets();
        unset($spreadSheet);
    }

    /**
     * 设置打印区域
     * 如果有多个表格，则纵向打印，否则横向打印
     */
    private function setPrint(Worksheet $worksheet, int $rowCount, int $colCount, int $tableCount)
    {
        if ($rowCount < 1 || $colCount < 1) {
            return;
        }

        $page = $worksheet->getPageSetup();

        // 纸张大小和方向
        $page->setOrientation($tableCount > 1 ? PageSetup::ORIENTATION_PORTRAIT : PageSetup::ORIENTATION_LANDSCAPE);
        $page->setPaperSize(PageSetup::PAPERSIZE_A4);
        $page->setPrintArea('A1:' . Coordinate::stringFromColumnIndex($colCount) . $rowCount);
        $page->setHorizontalCentered(true);
        $page->setVerticalCentered(false);
    }

    /**
     * 生成 excel 模板
     * @return array [当前行号, 当前列号, 行映射, 列映射]
     */
    private function createSheetTpl(Worksheet $activeSheet, Tpl $tpl, string $title, string $summary, array $header, string $headerAlign, int $currRowNum, int $colDefaultWidth = -1): array
    {
        // 必须有 template
        if (!$tpl) {
            throw new TargetException("缺少 template");
        }

        $rowHead = $tpl->rowHead();
        $colNum = $this->calcColNum($tpl);
        
        // 标题
        if ($title) {
            $this->setTitle($activeSheet, $title, $colNum, $currRowNum);
            $currRowNum++;
        }

        // 摘要
        if ($summary) {
            $this->setSummary($activeSheet, $summary, $colNum, $currRowNum);
            $currRowNum++;
        }

        // header
        if ($header) {
            $currRowNum += $this->setHeader($activeSheet, $header, $colNum, $currRowNum, $headerAlign);
        }

        // 列标头
        $colMap = $this->setColHead($activeSheet, $tpl->colHead(), $rowHead ? $rowHead->deep() - 1 : 0, $currRowNum, $colDefaultWidth);
        $currRowNum += $tpl->colHead()->deep() - 1;

        // 行标头
        $rowMap = [];
        if ($rowHead) {
            $rowMap = $this->setRowHead($activeSheet, $rowHead, $currRowNum);
        }

        return [$currRowNum, $rowHead ? $rowHead->deep() - 1 : 0, $rowMap, $colMap];
    }

    /**
     * 设置行标题
     * 每个节点对应一个格子
     * 需要确定每个节点在表格中的位置以及需要合并的行列数
     * 节点所在的层代表其所在的列
     * 非叶子节点只需要合并行，无需合并列；叶子节点只需要合并列，无需合并行
     * 非叶子节点拥有的叶子节点数目就是它要合并的行数
     * 叶子节点需要合并的列数等于树最大层 - 其所在的层
     * 行标题左侧节点在表格上面
     * 采用广度优先遍历
     * @return array 行映射表。格式：[行名 => [行号列表]]
     */
    private function setRowHead(Worksheet $worksheet, RowHead $rowHead, int $lastRowNum): array
    {
        return $this->setExcelSubHead($worksheet, $rowHead, 0, $lastRowNum, 2);
    }

    /**
     * 设置列标题
     * 每个节点对应一个格子
     * 需要确定每个节点在表格中的位置以及需要合并的行列数
     * 节点所在的层数代表它所在的行
     * 非叶节点只需要考虑合并列，叶节点需要同时考虑合并列和行，其中需要合并的行数=树最大层 - 其所在层
     * 非叶节点拥有的叶节点数目就是它要合并的列单元格数
     * 采用广度优先遍历
     * @param Worksheet $worksheet
     * @param ColHead $colHead 列表头树
     * @param int $rowHeadColNum 行表头占用的列数
     * @param int $lastRowNum 最新行号，需要从下一行开始
     * @param int $defaultColWidth
     * @return array 列映射表，格式：[列名 => 列号]
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function setColHead(Worksheet $worksheet, ColHead $colHead, int $rowHeadColNum, int $lastRowNum, int $defaultColWidth = -1): array
    {
        // 如果有行标题，则需要预留相应的列给行标题
        if ($rowHeadColNum) {
            $worksheet->mergeCells("A" . ($lastRowNum + 1) . ":"
            . Coordinate::stringFromColumnIndex($rowHeadColNum) . ($lastRowNum + $colHead->deep() - 1));
        }

        $colMap = $this->setExcelSubHead($worksheet, $colHead, $rowHeadColNum, $lastRowNum, 1, $defaultColWidth);

        return array_map(function ($item) {
            return $item[0];
        }, $colMap);
    }

    /**
     * 参见 setColHead(...) 的说明
     * @param Worksheet $worksheet
     * @param Node $headTree 行/列表头节点树
     * @param int $colOffset 列偏移量
     * @param int $rowOffset 行偏移量
     * @param int $type 1：生成列标题，2 生成行标题
     * @param int $colDefaultWidth
     * @return array 行列映射表。格式：[行/列名称 => [行/列号]]
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function setExcelSubHead(Worksheet $worksheet, Node $headTree, int $colOffset, int $rowOffset, int $type = 1, int $colDefaultWidth = -1): array
    {
        $depth = $headTree->deep() - 1;// 根节点不计入列表头深度
        $map = [];// 行列映射表。格式：[行/列名称 => [行/列号]]
        $styleMap = [];// 行列样式，格式：行/列号 => 样式对象

        // 使用队列实现广度优先遍历
        $queue = new SplQueue();
        $queue->enqueue($headTree);

        while (1) {
            // 退出遍历条件：队列为空
            if ($queue->isEmpty()) {
                break;
            }

            /**
             * 取出当前需要处理的节点
             * @var ColHead
             */
            $node = $queue->dequeue();

            // 顶层节点不对应任何单元格
            if ($node->name() == Node::NODE_TOP) {
                goto next;
            }

            $pos = $node->getPosition();

            /**
             * 将节点转化为单元格
             * 注意 merge 值等于 1 表示不需要合并（仅和它自身合并）
             */
            if ($type == 1) {
                // 该节点需要合并的列数等于该节点子树的广度
                $mergeColNum = $node->breadth();
                // 该节点需要合并的行数等于该节点的深度差(由于$depth已经减去1了，所以这里需要加1补回去)
                $mergeRowNum = $node->isLeaf() ? $depth - $pos[0] + 1 : 1;
            } else {
                // 行标题和列标题反过来
                $mergeColNum = $node->isLeaf() ? $depth - $pos[0] + 1 : 1;
                $mergeRowNum = $node->breadth();
            }
            
            // 设置单元格
            // 注意：树节点的位置从 0 开始的，要加 1（由于深度方向上已经去掉顶层节点了，所以此方向不需要再加 1）
            $fromRow = $rowOffset + ($type == 1 ? $pos[0] : $pos[1] + 1);
            $fromCol = $colOffset + ($type == 1 ? $pos[1] + 1 : $pos[0]);
            if ($mergeColNum > 1 || $mergeRowNum > 1) {
                $toRow = $fromRow + $mergeRowNum - 1;
                $toCol = $fromCol + $mergeColNum - 1;
                $worksheet->mergeCells(Coordinate::stringFromColumnIndex($fromCol)
                . $fromRow . ':' . Coordinate::stringFromColumnIndex($toCol) . $toRow);
            }

            $worksheet->getCell(Coordinate::stringFromColumnIndex($fromCol) . $fromRow)->setValue($node->title());

            // 叶子节点的特殊处理
            if ($node->isLeaf()) {
                // 保存行列映射
                if (!isset($map[$node->name()])) {
                    $map[$node->name()] = [];
                }

                if ($type == 1) {
                    $map[$node->name()][] = $fromCol;
                } elseif ($node instanceof RowHead) {
                    // 行映射，一个节点可能对应多行
                    for ($i = 0; $i < $node->rowCount(); $i++) {
                        $map[$node->name()][] = $fromRow + $i;
                    }
                }

                // 行列样式：列/行号=> Style
                $styleMap[$type == 1 ? $fromCol : $fromRow] = $node->style();
            }

            next:
            // 将该节点的孩子节点依次入列
            foreach ($node->children() as $child) {
                $queue->enqueue($child);
            }
        }

        // 设置表头样式
        $this->setCRHeadStyle(
            $worksheet,
            1,
            $rowOffset + 1,
            $type == 1 ? $colOffset + $headTree->breadth() : $depth,
            $rowOffset + ($type == 1 ? $depth : $headTree->breadth())
        );

        // 设置行列样式
        if ($type == 1) {
            $this->setColStyle($worksheet, $styleMap, $colDefaultWidth);
        } else {
            $this->setRowStyle($worksheet, $styleMap);
        }

        return $map;
    }

    /**
     * 设置列样式
     * 目前仅支持设置宽度
     */
    private function setColStyle(Worksheet $worksheet, array $colStyleMap, int $defaultWidth = -1)
    {
        foreach ($colStyleMap as $colNo => $style) {
            if (!$style || !$style instanceof Style) {
                continue;
            }

            $dm = $worksheet->getColumnDimension(Coordinate::stringFromColumnIndex($colNo));
            $width = $style->getWidth() ?: $defaultWidth;
            if ($width > 0) {
                $dm->setWidth($width);
            } else {
                // 负数表示自动列宽
                $dm->setAutoSize(true);
            }
        }
    }

    /**
     * 设置行样式
     */
    private function setRowStyle(Worksheet $worksheet, array $rowStyleMap)
    {
        // 暂时不设置任何行样式
    }

    private function setCRHeadStyle(Worksheet $worksheet, int $startCol, int $startRow, int $endCol, int $endRow)
    {
        $style = $worksheet->getStyle(Coordinate::stringFromColumnIndex($startCol) . $startRow
        . ':' . Coordinate::stringFromColumnIndex($endCol) . $endRow);
        $style->getFont()->setBold(true)->setSize(16);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('DFDFDF');
    }

    /**
     * 设置 Excel header
     * 第一版对 header 简化处理：全部排布在一行
     * @return int header 占用了几行
     */
    private function setHeader(Worksheet $worksheet, array $headers, int $colCount, int $lastRowNum, string $align = 'right'): int
    {
        $this->setSimpleHeaderFooter($worksheet, $headers, $colCount, $lastRowNum, $align);
        return 1;
    }

    /**
     * 设置 Excel Summary
     */
    private function setSummary(Worksheet $worksheet, string $summary, int $colCount, int $lastRowNum)
    {
        if (!$summary) {
            return;
        }

        $summary = str_ireplace(["<br>", "<br/>", "</br>"], "\n", $summary);

        $richText = new RichText();
        $richText->createText($summary);

        // 从下一行开始
        $currRowNum = $lastRowNum + 1;

        $coordinate = "A{$currRowNum}:" . Coordinate::stringFromColumnIndex($colCount) . $currRowNum;
        $worksheet->mergeCells($coordinate);
        $worksheet->getRowDimension($currRowNum)->setRowHeight($this->calcHeightWithLineCount(mb_substr_count($summary, "\n") + 1));
        $cell = $worksheet->getCell("A{$currRowNum}");
        // 自动换行
        $cell->getStyle()->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $cell->setValue($richText);
    }

    /**
     * 设置 Excel title
     */
    private function setTitle(Worksheet $worksheet, string $title, int $colCount, int $lastRowNum = 0)
    {
        if (!$title) {
            return;
        }

        // 从下一行开始
        $currRowNum = $lastRowNum + 1;

        $coordinate = "A{$currRowNum}:" . Coordinate::stringFromColumnIndex($colCount) . $currRowNum;
        $worksheet->mergeCells($coordinate);
        $worksheet->getRowDimension($currRowNum)->setRowHeight(36);
        $cell = $worksheet->getCell("A{$currRowNum}");
        $cell->setValue($title);
        $style = $cell->getStyle();
        $style->getFont()->setSize(22)->setBold(true);
        $style->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    }

    /**
     * 设置 Excel 页脚
     */
    private function setFooter(Worksheet $worksheet, array $footers, int $colCount, int $lastRowNum, string $align = 'right')
    {
        $this->setSimpleHeaderFooter($worksheet, $footers, $colCount, $lastRowNum, $align);
    }

    private function setSimpleHeaderFooter(Worksheet $worksheet, array $contents, int $colCount, int $lastRowNum, string $align = 'right')
    {
        $str = '';
        foreach ($contents as $key => $val) {
            $val = str_ireplace(["<br>", "<br/>", "</br>"], "\n", $val);
            $str .= "{$key}：{$val}";
            if (strpos($val, "\n") === false) {
                // 没有换行符，则后面加上空格分隔
                $str .= "        ";
            }
        }

        $richText = new RichText();
        $richText->createText($str);

        if (!in_array($align, [Alignment::HORIZONTAL_CENTER, Alignment::HORIZONTAL_LEFT, Alignment::HORIZONTAL_RIGHT])) {
            $align = Alignment::HORIZONTAL_RIGHT;
        }

        // 从下一行开始
        $currRowNum = $lastRowNum + 1;

        $coordinate = "A{$currRowNum}:" . Coordinate::stringFromColumnIndex($colCount) . $currRowNum;
        $worksheet->mergeCells($coordinate);
        $cell = $worksheet->getCell("A{$currRowNum}");
        $cell->setValue($richText);
        $cell->getStyle()->getAlignment()->setWrapText(true)->setHorizontal($align)->setVertical(Alignment::VERTICAL_CENTER);

        // 无法设置成自适应高度，需计算高度
        $worksheet->getRowDimension($currRowNum)->setRowHeight($this->calcHeightWithLineCount(mb_substr_count($str, "\n") + 1));
    }

    private function calcHeightWithLineCount(int $lineCount): int
    {
        return $lineCount == 1 ? 28 : 14 * $lineCount + 14;
    }

    /**
     * 设置 Excel 默认样式
     */
    private function setDefaultStyle(Spreadsheet $workSheet, ExcelTarget $target)
    {        
        $workSheet->getActiveSheet()->getDefaultRowDimension()->setRowHeight($target->getDefaultHeight());
    }

    /**
     * 计算 excel 列总数
     */
    private function calcColNum(Tpl $tpl): int
    {
        $cBreadth = $tpl->colHead()->breadth();
        $rDeep = $tpl->rowHead() ? $tpl->rowHead()->deep() - 1 : 0;
        return $cBreadth + $rDeep;
    }

    private function calcFileNames(string $origFileName, int $fileNum): array
    {
        if ($fileNum === 1) {
            return [$origFileName];
        }

        $fileNames = [];
        $fnameArr = explode('.', $origFileName);
        $ext = array_pop($fnameArr);
        $base = implode('.', $fnameArr);

        for ($i = 0; $i < $fileNum; $i++) {
            $fileNames[] = implode('', [$base, "_{$i}", ".{$ext}"]);
        }

        return $fileNames;
    }

    /**
     * 计算目标文件数目以及每个文件最大行数
     * @return array [文件数目, 最大行数]
     */
    private function calcFileCount(CSVSource $source, ExcelTarget $target): array
    {
        $tpls = $target->getTpls();

        // 多表格模式下只生成一个文件
        if (count($tpls) > 1) {
            return [1, PHP_INT_MAX];
        }

        // 有行标题的情况下只生成一个文件
        if (isset($tpls[0]) && $tpls[0] instanceof Tpl && $tpls[0]->rowHead()) {
            return [1, PHP_INT_MAX];
        }

        $maxSize = intval(Config::getInstance()->getConf("excel_max_size")) ?: 50 * 1024 * 1024;
        $maxCount = intval(Config::getInstance()->getConf("excel_max_count")) ?: 10000;
        $sourceSize = $source->size();
        $sourceCount = $source->count();

        if ($sourceSize <= $maxSize * 1.5 && $sourceCount <= $maxCount * 1.5) {
            return [1, PHP_INT_MAX];
        }

        $count = max(ceil($sourceSize / $maxSize), ceil($sourceCount / $maxCount));

        return [$count, ceil($sourceCount / $count)];
    }
}
