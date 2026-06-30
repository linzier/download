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
 * Excel file generator
 * Determines how many target files to generate based on the source file's row count and size.
 * If multiple target files are generated (or a single file exceeds a certain size), archive compression is applied.
 * Note: multi-table mode (multiple tables on one page, or multiple tabs) will not split into separate files.
 */
class ExcelGenerator
{
    /**
     * Column info: [column keys, column types, row header index position]
     */
    private $colInfo = [];

    public function generate(ISource $source, ExcelTarget $target, ICompress $compress = null)
    {
        if (!$source instanceof CSVSource) {
            throw new \Exception("generate csv error:need CSVSource type", ErrCode::SOURCE_TYPE_ERR);
        }

        if (!file_exists($source->fileName())) {
            throw new FileException("Source file does not exist: {$source->fileName()}", ErrCode::FILE_OP_FAILED);
        }

        // Calculate how many files to generate and the max rows per file, then determine each file name
        // Note: splitting is only possible when a compressor is provided; without one, no splitting occurs
        list($fileCount, $fileRowCount) = !$compress ? [1, PHP_INT_MAX] : $this->calcFileCount($source, $target);
        $fileNames = $this->calcFileNames($target->targetFileName(), $fileCount);

        if (!$sourceFile = @fopen($source->fileName(), 'rb')) {
            throw new FileException("Failed to open source file: {$source->fileName()}", ErrCode::FILE_OP_FAILED);
        }

        try {
            $this->extractColInfo($sourceFile);

            foreach ($fileNames as $index => $fileName) {
                // Generate excel. The last file has no row limit
                $maxRow = $index < count($fileNames) - 1 ? $fileRowCount : PHP_INT_MAX;
                $this->createExcel($sourceFile, $fileName, $maxRow, $target);
            }
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        } finally {
            fclose($sourceFile);
            // Delete source file
            unlink($source->fileName());
        }

        // Compress
        if ($compress && count($fileNames) > 1 || $source->size() > Config::getInstance()->getConf("zip_threshold")) {
            $newTargetFileName = $compress->compress(File::join($target->getBaseDir(), 'target'), $fileNames);
            // Reset target file name
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
        $rowHeadIndex = array_search(CSVSource::EXT_FIELD, $colTitles);// Row header index position (for tables with row headers)

        $this->colInfo = [$colTitles, $colTypes, $rowHeadIndex];
    }

    /**
     * Generate a table in the Excel sheet
     * @return array [row_offset, col_count]: row offset value, column count of this table
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
        // Generate template
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
        // Row header internal offset
        $rowHeadUsed = [];
        list($colTitles, $colTypes, $rowHeadIndex) = $this->colInfo;

        // Get styles for all columns
        $allCols = Node::fetchAllLeaves($tpl->colHead());
        $colStyles = [];
        foreach ($allCols as $colNode) {
            $colStyles[$colNode->name()] = $colNode->style();
        }

        // Read source data in a loop and write to excel
        while ($maxRow-- && !feof($sourceFile)) {
            if (!$rowValues = fgetcsv($sourceFile)) {
                continue;
            }

            if ($rowValues[0] === CSVSource::SPLIT_LINE) {
                // Encountered multi-source split line; fetch the next table's column info then break
                $this->extractColInfo($sourceFile);
                 break;
            }

            $rowOffset++;

            /**
             * Populate one row of data
             */
            // Determine the row number
            $theRowNum = $rowOffset;
            // If rowMap exists, use the row number from rowMap
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
            // Iterate through each column value and populate the excel cells
            // Note: the column count in the source data (rowValues) may not match the template; extra columns are ignored
            foreach ($rowValues as $index => $val) {
                // Determine the column number
                if (!isset($colTitles[$index]) || !$theColNum = ($colMap[$colTitles[$index]] ?? 0)) {
                    continue;
                }

                $cell = $activeSheet->getCell(Coordinate::stringFromColumnIndex($theColNum) . $theRowNum);
                
                // Cell type
                // Note: although we detected column types from the first row when generating the source CSV,
                // we re-detect here to handle cases where the same column has inconsistent types across rows
                $cellType = $colTypes[$index] == 'number' ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING;
                if (is_string($val) && !is_numeric($val)) {
                    $cellType = DataType::TYPE_STRING;
                }
                $cell->setValueExplicit($val, $cellType);

                // Cell style
                // Currently only alignment is supported; additional styles can be added later
                $style = $colStyles[$colTitles[$index]];
                if ($colAlign = $style->getAlign()) {
                    $cell->getStyle()->getAlignment()->setWrapText(true)->setHorizontal($colAlign)->setVertical(Alignment::VERTICAL_CENTER);
                }
            }

            // Set row height (using default row height has no effect)
            $activeSheet->getRowDimension($theRowNum)->setRowHeight($rowHeight);
        }

        // Shift colOffset to the end position
        $colOffset += count($colMap);

        // Set borders for the entire table (title row is excluded)
        $activeSheet->getStyle("A" . ($title ? $startRowOffset + 1 : $startRowOffset) . ':' . Coordinate::stringFromColumnIndex($colOffset) . $rowOffset)
        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Set footer
        if ($footer) {
            $this->setFooter($activeSheet, $footer, $colOffset, $rowOffset, $footerAlign);
        }

        return [++$rowOffset, $colOffset];
    }

    /**
     * Generate an excel file
     * Multiple tables may be created within a single excel file
     * @param resource $sourceFile Source data file resource
     * @param string $targetFileName Target file name
     * @param int $maxRow Maximum number of rows to read
     * @param ExcelTarget $target Target object
     */
    private function createExcel($sourceFile, string $targetFileName, int $maxRow, ExcelTarget $target)
    {
        $spreadSheet = new Spreadsheet();
        $activeSheet = $spreadSheet->getActiveSheet();
        $this->setDefaultStyle($spreadSheet, $target);

        $rowOffset = $target->rowOffset() ?? 0;
        $tableIndex = 0;
        $maxColCount = 0;// Maximum column count
        while (!feof($sourceFile) && $target->getTpls($tableIndex) && $rowOffset < $maxRow) {
            // Generate one table per iteration
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

            // After each table, shift the row offset down by 3 rows
            $rowOffset += 3;
            $tableIndex++;

            $maxColCount = max($maxColCount, $colCount);
        }

        // Set print area
        $this->setPrint($activeSheet, $rowOffset - 3, $maxColCount, $tableIndex);

        $writer = new Xlsx($spreadSheet);
        $writer->save($targetFileName);

        $spreadSheet->disconnectWorksheets();
        unset($spreadSheet);
    }

    /**
     * Set print area
     * If there are multiple tables, print in portrait orientation; otherwise, landscape
     */
    private function setPrint(Worksheet $worksheet, int $rowCount, int $colCount, int $tableCount)
    {
        if ($rowCount < 1 || $colCount < 1) {
            return;
        }

        $page = $worksheet->getPageSetup();

        // Paper size and orientation
        $page->setOrientation($tableCount > 1 ? PageSetup::ORIENTATION_PORTRAIT : PageSetup::ORIENTATION_LANDSCAPE);
        $page->setPaperSize(PageSetup::PAPERSIZE_A4);
        $page->setPrintArea('A1:' . Coordinate::stringFromColumnIndex($colCount) . $rowCount);
        $page->setHorizontalCentered(true);
        $page->setVerticalCentered(false);
    }

    /**
     * Generate excel template
     * @return array [current row number, current column number, row mapping, column mapping]
     */
    private function createSheetTpl(Worksheet $activeSheet, Tpl $tpl, string $title, string $summary, array $header, string $headerAlign, int $currRowNum, int $colDefaultWidth = -1): array
    {
        // Template is required
        if (!$tpl) {
            throw new TargetException("Missing template");
        }

        $rowHead = $tpl->rowHead();
        $colNum = $this->calcColNum($tpl);

        // Title
        if ($title) {
            $this->setTitle($activeSheet, $title, $colNum, $currRowNum);
            $currRowNum++;
        }

        // Summary
        if ($summary) {
            $this->setSummary($activeSheet, $summary, $colNum, $currRowNum);
            $currRowNum++;
        }

        // Header
        if ($header) {
            $currRowNum += $this->setHeader($activeSheet, $header, $colNum, $currRowNum, $headerAlign);
        }

        // Column headers
        $colMap = $this->setColHead($activeSheet, $tpl->colHead(), $rowHead ? $rowHead->deep() - 1 : 0, $currRowNum, $colDefaultWidth);
        $currRowNum += $tpl->colHead()->deep() - 1;

        // Row headers
        $rowMap = [];
        if ($rowHead) {
            $rowMap = $this->setRowHead($activeSheet, $rowHead, $currRowNum);
        }

        return [$currRowNum, $rowHead ? $rowHead->deep() - 1 : 0, $rowMap, $colMap];
    }

    /**
     * Set row headers
     * Each node corresponds to one cell.
     * We need to determine each node's position in the table and the number of rows/columns to merge.
     * The layer of a node represents its column.
     * Non-leaf nodes only need row merging (no column merging); leaf nodes only need column merging (no row merging).
     * The number of leaf nodes a non-leaf node has equals the number of rows to merge.
     * The number of columns a leaf node needs to merge equals the tree's maximum depth minus the node's own depth.
     * Left-side nodes in the row header appear at the top of the table.
     * Uses breadth-first traversal.
     * @return array Row mapping table. Format: [row name => [row number list]]
     */
    private function setRowHead(Worksheet $worksheet, RowHead $rowHead, int $lastRowNum): array
    {
        return $this->setExcelSubHead($worksheet, $rowHead, 0, $lastRowNum, 2);
    }

    /**
     * Set column headers
     * Each node corresponds to one cell.
     * We need to determine each node's position in the table and the number of rows/columns to merge.
     * The depth of a node represents its row.
     * Non-leaf nodes only need column merging; leaf nodes need both column and row merging,
     * where the number of rows to merge = tree max depth - node's depth.
     * The number of leaf nodes a non-leaf node has equals the number of column cells to merge.
     * Uses breadth-first traversal.
     * @param Worksheet $worksheet
     * @param ColHead $colHead Column header tree
     * @param int $rowHeadColNum Number of columns occupied by the row header
     * @param int $lastRowNum Latest row number; writing starts from the next row
     * @param int $defaultColWidth
     * @return array Column mapping table. Format: [column name => column number]
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function setColHead(Worksheet $worksheet, ColHead $colHead, int $rowHeadColNum, int $lastRowNum, int $defaultColWidth = -1): array
    {
        // If there is a row header, reserve the corresponding columns for it
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
     * See setColHead(...) for details
     * @param Worksheet $worksheet
     * @param Node $headTree Row/column header node tree
     * @param int $colOffset Column offset
     * @param int $rowOffset Row offset
     * @param int $type 1: generate column headers, 2: generate row headers
     * @param int $colDefaultWidth
     * @return array Row/column mapping table. Format: [row/column name => [row/column number]]
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function setExcelSubHead(Worksheet $worksheet, Node $headTree, int $colOffset, int $rowOffset, int $type = 1, int $colDefaultWidth = -1): array
    {
        $depth = $headTree->deep() - 1;// Root node is not counted in column header depth
        $map = [];// Row/column mapping. Format: [row/column name => [row/column number]]
        $styleMap = [];// Row/column styles. Format: row/column number => Style object

        // Use a queue for breadth-first traversal
        $queue = new SplQueue();
        $queue->enqueue($headTree);

        while (1) {
            // Exit traversal when the queue is empty
            if ($queue->isEmpty()) {
                break;
            }

            /**
             * Dequeue the current node to process
             * @var ColHead
             */
            $node = $queue->dequeue();

            // Top-level node does not correspond to any cell
            if ($node->name() == Node::NODE_TOP) {
                goto next;
            }

            $pos = $node->getPosition();

            /**
             * Convert node to cell
             * Note: a merge value of 1 means no merging is needed (only merges with itself)
             */
            if ($type == 1) {
                // The number of columns to merge equals the breadth of this node's subtree
                $mergeColNum = $node->breadth();
                // The number of rows to merge equals the depth difference (since $depth is already decremented by 1, we add 1 back here)
                $mergeRowNum = $node->isLeaf() ? $depth - $pos[0] + 1 : 1;
            } else {
                // Row and column headers are reversed
                $mergeColNum = $node->isLeaf() ? $depth - $pos[0] + 1 : 1;
                $mergeRowNum = $node->breadth();
            }
            
            // Set cell
            // Note: tree node positions start from 0, so add 1 (the top-level node has already been removed in the depth direction, so no +1 needed there)
            $fromRow = $rowOffset + ($type == 1 ? $pos[0] : $pos[1] + 1);
            $fromCol = $colOffset + ($type == 1 ? $pos[1] + 1 : $pos[0]);
            if ($mergeColNum > 1 || $mergeRowNum > 1) {
                $toRow = $fromRow + $mergeRowNum - 1;
                $toCol = $fromCol + $mergeColNum - 1;
                $worksheet->mergeCells(Coordinate::stringFromColumnIndex($fromCol)
                . $fromRow . ':' . Coordinate::stringFromColumnIndex($toCol) . $toRow);
            }

            $worksheet->getCell(Coordinate::stringFromColumnIndex($fromCol) . $fromRow)->setValue($node->title());

            // Special handling for leaf nodes
            if ($node->isLeaf()) {
                // Save row/column mapping
                if (!isset($map[$node->name()])) {
                    $map[$node->name()] = [];
                }

                if ($type == 1) {
                    $map[$node->name()][] = $fromCol;
                } elseif ($node instanceof RowHead) {
                    // Row mapping: a single node may correspond to multiple rows
                    for ($i = 0; $i < $node->rowCount(); $i++) {
                        $map[$node->name()][] = $fromRow + $i;
                    }
                }

                // Row/column style: column/row number => Style
                $styleMap[$type == 1 ? $fromCol : $fromRow] = $node->style();
            }

            next:
            // Enqueue child nodes
            foreach ($node->children() as $child) {
                $queue->enqueue($child);
            }
        }

        // Set header styles
        $this->setCRHeadStyle(
            $worksheet,
            1,
            $rowOffset + 1,
            $type == 1 ? $colOffset + $headTree->breadth() : $depth,
            $rowOffset + ($type == 1 ? $depth : $headTree->breadth())
        );

        // Set row/column styles
        if ($type == 1) {
            $this->setColStyle($worksheet, $styleMap, $colDefaultWidth);
        } else {
            $this->setRowStyle($worksheet, $styleMap);
        }

        return $map;
    }

    /**
     * Set column styles
     * Currently only width is supported
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
                // Negative value means auto column width
                $dm->setAutoSize(true);
            }
        }
    }

    /**
     * Set row styles
     */
    private function setRowStyle(Worksheet $worksheet, array $rowStyleMap)
    {
        // No row styles are set for now
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
     * Set Excel header
     * First version uses a simplified approach: all headers are placed in a single row
     * @return int Number of rows occupied by the header
     */
    private function setHeader(Worksheet $worksheet, array $headers, int $colCount, int $lastRowNum, string $align = 'right'): int
    {
        $this->setSimpleHeaderFooter($worksheet, $headers, $colCount, $lastRowNum, $align);
        return 1;
    }

    /**
     * Set Excel Summary
     */
    private function setSummary(Worksheet $worksheet, string $summary, int $colCount, int $lastRowNum)
    {
        if (!$summary) {
            return;
        }

        $summary = str_ireplace(["<br>", "<br/>", "</br>"], "\n", $summary);

        $richText = new RichText();
        $richText->createText($summary);

        // Start from the next row
        $currRowNum = $lastRowNum + 1;

        $coordinate = "A{$currRowNum}:" . Coordinate::stringFromColumnIndex($colCount) . $currRowNum;
        $worksheet->mergeCells($coordinate);
        $worksheet->getRowDimension($currRowNum)->setRowHeight($this->calcHeightWithLineCount(mb_substr_count($summary, "\n") + 1));
        $cell = $worksheet->getCell("A{$currRowNum}");
        // Auto wrap text
        $cell->getStyle()->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $cell->setValue($richText);
    }

    /**
     * Set Excel title
     */
    private function setTitle(Worksheet $worksheet, string $title, int $colCount, int $lastRowNum = 0)
    {
        if (!$title) {
            return;
        }

        // Start from the next row
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
     * Set Excel footer
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
            $str .= "{$key}: {$val}";
            if (strpos($val, "\n") === false) {
                // No line break found; add spaces as separator
                $str .= "        ";
            }
        }

        $richText = new RichText();
        $richText->createText($str);

        if (!in_array($align, [Alignment::HORIZONTAL_CENTER, Alignment::HORIZONTAL_LEFT, Alignment::HORIZONTAL_RIGHT])) {
            $align = Alignment::HORIZONTAL_RIGHT;
        }

        // Start from the next row
        $currRowNum = $lastRowNum + 1;

        $coordinate = "A{$currRowNum}:" . Coordinate::stringFromColumnIndex($colCount) . $currRowNum;
        $worksheet->mergeCells($coordinate);
        $cell = $worksheet->getCell("A{$currRowNum}");
        $cell->setValue($richText);
        $cell->getStyle()->getAlignment()->setWrapText(true)->setHorizontal($align)->setVertical(Alignment::VERTICAL_CENTER);

        // Cannot set auto height; must calculate manually
        $worksheet->getRowDimension($currRowNum)->setRowHeight($this->calcHeightWithLineCount(mb_substr_count($str, "\n") + 1));
    }

    private function calcHeightWithLineCount(int $lineCount): int
    {
        return $lineCount == 1 ? 28 : 14 * $lineCount + 14;
    }

    /**
     * Set Excel default styles
     */
    private function setDefaultStyle(Spreadsheet $workSheet, ExcelTarget $target)
    {        
        $workSheet->getActiveSheet()->getDefaultRowDimension()->setRowHeight($target->getDefaultHeight());
    }

    /**
     * Calculate total number of Excel columns
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
     * Calculate the number of target files and the max rows per file
     * @return array [file count, max row count]
     */
    private function calcFileCount(CSVSource $source, ExcelTarget $target): array
    {
        $tpls = $target->getTpls();

        // In multi-table mode, only generate one file
        if (count($tpls) > 1) {
            return [1, PHP_INT_MAX];
        }

        // When row headers are present, only generate one file
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
