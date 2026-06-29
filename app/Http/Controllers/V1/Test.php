<?php

namespace App\Http\Controllers\V1;

use App\Domain\Processor\Ticket;
use App\Domain\Transfer\Upload;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use WecarSwoole\Http\Controller;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Swoole\Coroutine;
use WecarSwoole\Client\API;
use WecarSwoole\Util\File;
use WecarSwoole\Util\Url;

class Test extends Controller
{
    public function index()
    {
        ini_set("memory_limit", "1024M");

        $spreadsheet = new Spreadsheet();

        // 使用列缓存
        $cache = new MyCustomPsr16Implementation();
        \PhpOffice\PhpSpreadsheet\Settings::setCache($cache);

        // 设置语言
        $locale = 'pt_br';
        $validLocale = \PhpOffice\PhpSpreadsheet\Settings::setLocale($locale);
        if (!$validLocale) {
            echo 'Unable to set locale to ' . $locale . " - reverting to en_us" . PHP_EOL;
        }

        // 获取指定的 worksheet
        $spreadsheet->getSheet(1);
        $spreadsheet->getSheetByName('Worksheet 1');
        $spreadsheet->getActiveSheet();
        $spreadsheet->getSheetCount();
        $spreadsheet->getSheetNames();
        $spreadsheet->setActiveSheetIndex(1);
        $spreadsheet->setActiveSheetIndexByName('name');

        // 根据下标获取单元格，下标从 1 开始
        $spreadsheet->getActiveSheet()->getCellByColumnAndRow(2, 5)->getValue();
        $letter = Coordinate::stringFromColumnIndex(2);

        // 通过数组设置单元格值
        $arrayData = [
            [NULL, 2010, 2011, 2012],
            ['Q1',   12,   15,   21],
            ['Q2',   56,   73,   86],
            ['Q3',   52,   61,   69],
            ['Q4',   30,   32,    0],
        ];
        $spreadsheet->getActiveSheet()
        ->fromArray(
            $arrayData,  // The data to set
            NULL,        // Array values with this value will not be set
            'C3'         // Top left coordinate of the worksheet range where
                        //    we want to set these values (default is A1)
        );

        // 创建 worksheet
        $spreadsheet->createSheet();
        // Create a new worksheet called "My Data"
        $myWorkSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'My Data');
        // Attach the "My Data" worksheet as the first worksheet in the Spreadsheet object
        $spreadsheet->addSheet($myWorkSheet, 0);

        $clonedWorksheet = clone $spreadsheet->getSheetByName('Worksheet 1');
        $clonedWorksheet->setTitle('Copy of Worksheet 1');
        $spreadsheet->addSheet($clonedWorksheet);

        // 删除 worksheet
        $sheetIndex = $spreadsheet->getIndex(
            $spreadsheet->getSheetByName('Worksheet 1')
        );
        $spreadsheet->removeSheetByIndex($sheetIndex);

        // 设置元数据
        $spreadsheet->getProperties()
        ->setCreator("Maarten Balliauw")
        ->setLastModifiedBy("Maarten Balliauw")
        ->setTitle("Office 2007 XLSX Test Document")
        ->setSubject("Office 2007 XLSX Test Document")
        ->setDescription(
            "Test document for Office 2007 XLSX, generated using PHP classes."
        )
        ->setKeywords("office 2007 openxml php")
        ->setCategory("Test result file");

        // 设置文本换行
        $spreadsheet->getActiveSheet()->getCell('A1')->setValue("hello\nworld");
        $spreadsheet->getActiveSheet()->getStyle('A1')->getAlignment()->setWrapText(true);

        // 精确设置单元格格式
        $spreadsheet->getActiveSheet()->getCell('A1')
        ->setValueExplicit(
            '25',
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
        );

        // 设置超链接
        $spreadsheet->getActiveSheet()->setCellValue('E26', 'www.phpexcel.net');
        $spreadsheet->getActiveSheet()->getCell('E26')->getHyperlink()->setUrl('https://www.example.com');

        // 链接到另一个 worksheet
        $spreadsheet->getActiveSheet()->setCellValue('E26', 'www.phpexcel.net');
        $spreadsheet->getActiveSheet()->getCell('E26')->getHyperlink()->setUrl("sheet://'Sheetname'!A1");

        // 设置打印格式（方向、纸张大小）
            $spreadsheet->getActiveSheet()->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $spreadsheet->getActiveSheet()->getPageSetup()
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

        // 设置打印格式：宽高适配
        $spreadsheet->getActiveSheet()->getPageSetup()->setFitToWidth(1);
        $spreadsheet->getActiveSheet()->getPageSetup()->setFitToHeight(0);
        $spreadsheet->getActiveSheet()->getPageSetup()->setScale(100);

        // 设置打印格式：页边距
        $spreadsheet->getActiveSheet()->getPageMargins()->setTop(1);
        $spreadsheet->getActiveSheet()->getPageMargins()->setRight(0.75);
        $spreadsheet->getActiveSheet()->getPageMargins()->setLeft(0.75);
        $spreadsheet->getActiveSheet()->getPageMargins()->setBottom(1);

        // 设置打印格式：居中
        $spreadsheet->getActiveSheet()->getPageSetup()->setHorizontalCentered(true);
        $spreadsheet->getActiveSheet()->getPageSetup()->setVerticalCentered(false);

        // 打印格式：页眉页脚
            $spreadsheet->getActiveSheet()->getHeaderFooter()
            ->setOddHeader('&C&HPlease treat this document as confidential!');
        $spreadsheet->getActiveSheet()->getHeaderFooter()
            ->setOddFooter('&L&B' . $spreadsheet->getProperties()->getTitle() . '&RPage &P of &N');

        // 打印格式：设置打印区域
        $spreadsheet->getActiveSheet()->getPageSetup()->setPrintArea('A1:E5');

        // 设置单元格格式
        $spreadsheet->getActiveSheet()->getStyle('B2')
            ->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED);
        $spreadsheet->getActiveSheet()->getStyle('B2')
            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $spreadsheet->getActiveSheet()->getStyle('B2')
            ->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
        $spreadsheet->getActiveSheet()->getStyle('B2')
            ->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
        $spreadsheet->getActiveSheet()->getStyle('B2')
            ->getBorders()->getLeft()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
        $spreadsheet->getActiveSheet()->getStyle('B2')
            ->getBorders()->getRight()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK);
        $spreadsheet->getActiveSheet()->getStyle('B2')
            ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $spreadsheet->getActiveSheet()->getStyle('B2')
            ->getFill()->getStartColor()->setARGB('FFFF0000');
        // 设置多个单元格格式（推荐）
        $spreadsheet->getActiveSheet()->getStyle('B3:B7')->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFFF0000');
        // 通过数组设置（设置数量很多的时候有性能优势）
        $styleArray = [
            'font' => [
                'bold' => true,
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
            ],
            'borders' => [
                'top' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
                'rotation' => 90,
                'startColor' => [
                    'argb' => 'FFA0A0A0',
                ],
                'endColor' => [
                    'argb' => 'FFFFFFFF',
                ],
            ],
        ];
        $spreadsheet->getActiveSheet()->getStyle('A3')->applyFromArray($styleArray);
        // 设置默认样式
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial');
        $spreadsheet->getDefaultStyle()->getFont()->setSize(8);
        $spreadsheet->getActiveSheet()->getDefaultColumnDimension()->setWidth(12);
        $spreadsheet->getActiveSheet()->getDefaultRowDimension()->setRowHeight(15);

        // 条件样式
        $conditional1 = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
        $conditional1->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
        $conditional1->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_LESSTHAN);
        $conditional1->addCondition('0');
        $conditional1->getStyle()->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_RED);
        $conditional1->getStyle()->getFont()->setBold(true);
        $conditional2 = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
        $conditional2->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
        $conditional2->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_GREATERTHANOREQUAL);
        $conditional2->addCondition('0');
        $conditional2->getStyle()->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_GREEN);
        $conditional2->getStyle()->getFont()->setBold(true);
        $conditionalStyles = $spreadsheet->getActiveSheet()->getStyle('B2')->getConditionalStyles();
        $conditionalStyles[] = $conditional1;
        $conditionalStyles[] = $conditional2;
        $spreadsheet->getActiveSheet()->getStyle('B2')->setConditionalStyles($conditionalStyles);

        // 重用样式
        $spreadsheet->getActiveSheet()
        ->duplicateStyle(
            $spreadsheet->getActiveSheet()->getStyle('B2'),
            'B3:B7'
        );

        // 设置列过滤
        $spreadsheet->getActiveSheet()->setAutoFilter('A1:C9');

        // 设置列宽度
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);// 自动宽度

        // 设置行高
        $spreadsheet->getActiveSheet()->getRowDimension('10')->setRowHeight(100);//默认是 12.75 pts

        // 合并单元格
        $spreadsheet->getActiveSheet()->mergeCells('A18:E22');

        // 添加图片
        //Use GD to create an in-memory image
        $gdImage = @imagecreatetruecolor(120, 20) or die('Cannot Initialize new GD image stream');
        $textColor = imagecolorallocate($gdImage, 255, 255, 255);
        imagestring($gdImage, 1, 5, 5,  'Created with PhpSpreadsheet', $textColor);
        //  Add the In-Memory image to a worksheet
        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing();
        $drawing->setName('In-Memory image 1');
        $drawing->setDescription('In-Memory image 1');
        $drawing->setCoordinates('G10');
        $drawing->setImageResource($gdImage);
        $drawing->setRenderingFunction(
            \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::RENDERING_JPEG
        );
        $drawing->setMimeType(\PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::MIMETYPE_DEFAULT);
        $drawing->setHeight(36);
        $drawing->setWorksheet($spreadsheet->getActiveSheet());


        // 数据格式化
        $spreadsheet->getActiveSheet()->getStyle('A1')->getNumberFormat()
        ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

        // Set value binder
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder() );

        // Create new Spreadsheet object
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        for ($i = 1; $i < 400; $i++) {
            $spreadsheet->getActiveSheet()->setCellValue("A{$i}", 'hello word');
            $spreadsheet->getActiveSheet()->setCellValue("B{$i}", 'hello word');
            $spreadsheet->getActiveSheet()->setCellValue("C{$i}", 'hello word');
            $spreadsheet->getActiveSheet()->setCellValue("D{$i}", 'hello word');
            $spreadsheet->getActiveSheet()->setCellValue("E{$i}", 'hello word');
            $spreadsheet->getActiveSheet()->setCellValue("F{$i}", 'hello word');
        }



        $writer = new Xlsx($spreadsheet);
        $writer->save(File::join(STORAGE_ROOT, 'temp/hello world.xlsx'));

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    public function sourceData()
    {
        $data = [
            [
                'name' => '张三',
                'age' => mt_rand(10, 100).'',
                'sex' => '男',
                'love_in' => '乒乓球',
                'love_out_land' => '跑步',
                'love_out_sky' => '跳伞',
                'city' => ['深圳', '上海'][mt_rand(0,1)],
                'area' => '区域名称',
                'building' => '小区名',
                '_row_head_' => 'front_end',
            ],
            [
                'name' => '张四',
                'age' => mt_rand(10, 100),
                'sex' => '男',
                'love_in' => '乒乓球',
                'love_out_land' => '跑步',
                'love_out_sky' => '跳伞',
                'city' => ['深圳', '上海'][mt_rand(0,1)],
                'area' => '区域名称',
                'building' => '小区名',
                '_row_head_' => 'front_end',
            ],
            [
                'name' => '张五',
                'age' => mt_rand(10, 100),
                'sex' => '男',
                'love_in' => '乒乓球',
                'love_out_land' => '跑步',
                'love_out_sky' => '跳伞',
                'city' => ['深圳', '上海'][mt_rand(0,1)],
                'area' => '区域名称',
                'building' => '小区名',
                '_row_head_' => 'back_end',
            ],
            [
                'name' => '张六',
                'age' => mt_rand(10, 100),
                'sex' => '男',
                'love_in' => '乒乓球',
                'love_out_land' => '跑步',
                'love_out_sky' => '跳伞',
                'city' => ['深圳', '上海'][mt_rand(0,1)],
                'area' => '区域名称',
                'building' => '小区名',
                '_row_head_' => 'back_end',
            ],
            [
                'name' => '张七',
                'age' => mt_rand(10, 100),
                'sex' => '男',
                'love_in' => '乒乓球',
                'love_out_land' => '跑步',
                'love_out_sky' => '跳伞',
                'city' => ['深圳', '上海'][mt_rand(0,1)],
                'area' => '区域名称',
                'building' => '小区名',
                '_row_head_' => 'back_end',
            ],
            [
                'name' => '张八',
                'age' => mt_rand(10, 100),
                'sex' => '男',
                'love_in' => '乒乓球',
                'love_out_land' => '跑步',
                'love_out_sky' => '跳伞',
                'city' => ['深圳', '上海'][mt_rand(0,1)],
                'area' => '区域名称',
                'building' => '小区名',
                '_row_head_' => 'back_end',
            ],
            [
                'name' => '张九',
                'age' => mt_rand(10, 100),
                'sex' => '男',
                'love_in' => '乒乓球',
                'love_out_land' => '跑步',
                'love_out_sky' => '跳伞',
                'city' => ['深圳', '上海'][mt_rand(0,1)],
                'area' => '区域名称',
                'building' => '小区名',
                '_row_head_' => 'os',
            ],
            [
                'name' => '张十',
                'age' => mt_rand(10, 100),
                'sex' => '男',
                'love_in' => '乒乓球',
                'love_out_land' => '跑步',
                'love_out_sky' => '跳伞',
                'city' => ['深圳', '上海'][mt_rand(0,1)],
                'area' => '区域名称',
                'building' => '小区名',
                '_row_head_' => 'os',
            ],
            [
                'name' => '张十一',
                'age' => mt_rand(10, 100),
                'sex' => '男',
                'love_in' => '乒乓球',
                'love_out_land' => '跑步',
                'love_out_sky' => '跳伞',
                'city' => ['深圳', '上海'][mt_rand(0,1)],
                'area' => '区域名称',
                'building' => '小区名',
                '_row_head_' => 'os',
            ],
            [
                'name' => '张十二',
                'age' => mt_rand(10, 100),
                'sex' => '男',
                'love_in' => '乒乓球',
                'love_out_land' => '跑步',
                'love_out_sky' => '跳伞',
                'city' => ['深圳', '上海'][mt_rand(0,1)],
                'area' => '区域名称',
                'building' => '小区名',
                '_row_head_' => 'pos',
            ],
            [
                'name' => '张十三',
                'age' => mt_rand(10, 100),
                'sex' => '男',
                'love_in' => '乒乓球',
                'love_out_land' => '跑步',
                'love_out_sky' => '跳伞',
                'city' => ['深圳', '上海'][mt_rand(0,1)],
                'area' => '区域名称',
                'building' => '小区名',
                '_row_head_' => 'pos',
            ],
            [
                'name' => '张十四',
                'age' => mt_rand(10, 100),
                'sex' => '男',
                'love_in' => '乒乓球',
                'love_out_land' => '跑步',
                'love_out_sky' => '跳伞',
                'city' => ['深圳', '上海'][mt_rand(0,1)],
                'area' => '区域名称',
                'building' => '小区名',
                '_row_head_' => 'screen',
            ],
            [
                'name' => '张十五',
                'age' => mt_rand(10, 100),
                'sex' => '男',
                'love_in' => '乒乓球',
                'love_out_land' => '跑步',
                'love_out_sky' => '跳伞',
                'city' => ['深圳', '上海'][mt_rand(0,1)],
                'area' => '区域名称',
                'building' => '小区名',
                '_row_head_' => 'screen',
            ],
            [
                'name' => '张十六',
                'age' => 12345678901,
                'sex' => '男',
                'love_in' => '乒乓球',
                'love_out_land' => '跑步',
                'love_out_sky' => '跳伞',
                'city' => ['深圳', '上海'][mt_rand(0,1)],
                'area' => '区域名称',
                'building' => '小区名',
                '_row_head_' => 'screen',
            ],
        ];
        $page = $this->params('page') ?: 0;
        // $data = [];
        // for ($i = 0; $i < 1000; $i++) {
        //     $data[] = [
        //         'name' => "张三{$i}-{$page}",
        //         'age' => mt_rand(10, 100),
        //         'sex' => ['男', '女'][mt_rand(0,1)],
        //         'love_in' => ['乒乓球', '羽毛球'][mt_rand(0,1)],
        //         'love_out_land' => ['跑步', '爬山'][mt_rand(0,1)],
        //         'love_out_sky' => '跳伞',
        //         'city' => ['深圳', '上海'][mt_rand(0,1)],
        //         'area' => '区域名称',
        //         'building' => '小区名'
        //     ];
        // }
//        $force = mt_rand(0, 10);

        $this->return([
             'data' => $data,
//                'force_continue' => $force,
//            "data" => [
//                [
//                    "name" => '按',
//                    "age" => 43
//                ]
//            ],
//            'data' => [],
//            'total' => 0,
            // 'header' => ["油站" => '钓鱼岛', '日期' => date('Y-m-d')],
            // 'footer' => ['负责人' => '松林', '总监签名' => '', 'CEO 签名' => ''],
//            'template' => ['name' => '姓名', 'sex' => '性别']
//             'template' => [
//                 'row' => [
//                     [
//                         'title' => '云研发',
//                         'children' => [
//                             [
//                                 'name' => 'front_end',
//                                 'title' => '前端',
//                                 'row_count' => 2,
//                             ],
//                             [
//                                 'name' => 'back_end',
//                                 'title' => '后端',
//                                 'row_count' => 4,
//                             ],
//                         ]
//                     ],
//                     [
//                         'title' => 'OS及智能设备',
//                         'children' => [
//                             [
//                                 'title' => 'OS',
//                                 'name' => 'os',
//                                 'row_count' => 3,
//                             ],
//                             [
//                                 'title' => '智能设备',
//                                 'children' => [
//                                     [
//                                         'name' => 'pos',
//                                         'title' => '手持终端',
//                                         'row_count' => 2,
//                                     ],
//                                     [
//                                         'name' => 'screen',
//                                         'title' => '大屏',
//                                         'row_count' => 3,
//                                     ],
//                                 ]
//                             ]
//                         ]
//                     ]
//                 ],
//                 'col' => [
//                     [
//                         'title' => '人员',
//                         'children' => [
//                             [
//                                 'name' => 'name',
//                                 'title' => '姓名',
//                                 'type' => 'string',
//                                 'color' => 'red',
//                                 "width" => -1
//                             ],
//                             [
//                                 'title' => '其它',
//                                 'children' => [
//                                     [
//                                         'name' => 'age',
//                                         'title' => '年龄',
//                                         'type' => 'number',
//                                     ],
//                                     [
//                                         'name' => 'sex',
//                                         'title' => '性别',
//                                         'type' => 'string',
//                                         'width' => 8,
//                                     ],
//                                     [
//                                         'title' => '爱好',
//                                         'children' => [
//                                             [
//                                                 'name' => 'love_in',
//                                                 'title' => '室内',
//                                             ],
//                                             [
//                                                 'title' => '室外',
//                                                 'children' => [
//                                                     [
//                                                         'name' => 'love_out_land',
//                                                         'title' => '陆地',
//                                                     ],
//                                                     [
//                                                         'name' => 'love_out_sky',
//                                                         'title' => '空中',
//                                                     ],
//                                                 ]
//                                             ],
//                                         ]
//                                     ]
//                                 ]
//                             ]
//                         ]
//                     ],
//                     [
//                         'title' => '住址',
//                         'children' => [
//                             [
//                                 'name' => 'city',
//                                 'title' => '城市'
//                             ],
//                             [
//                                 'title' => '小区',
//                                 'children' => [
//                                     [
//                                         'name' => 'area',
//                                         'title' => '区域',
//                                     ],
//                                     [
//                                         'name' => 'building',
//                                         'title' => '楼盘',
//                                     ]
//                                 ]
//                             ]
//                         ]
//                     ],
//                 ],
//                 'col_' => [
//                     'name' => '名字',
//                     'age' => '年龄',
//                     'sex' => '性别',
//                     'love_in' => '室内爱好',
//                     'love_out_land' => '室外陆地爱好',
//                     'love_out_sky' => '室外空中爱好',
//                     'city' => '城市',
//                     'area' => '区域',
//                     'building' => '小区',
//                 ]
//             ]
        ]);
    }

    /**
     * 测试创建大文件
     */
    public function createBigFile()
    {
        set_time_limit(0);
        $f = fopen(File::join(STORAGE_ROOT, "temp/big_file.csv"), 'w');
        
        for ($i = 0; $i < 6000000; $i++) {
            fputcsv($f, ["张松林","张松林","张松林","张松林","张松林","张松林","张松林","张松林","张松林","张松林","张松林","张松林","张松林",]);
        }
        fclose($f);
    }

    /**
     * 测试下载大文件
     */
    public function download()
    {
        set_time_limit(0);
        $fileName = File::join(STORAGE_ROOT, "temp/big_file.csv");
        $title = "测试大文件.csv";
        $this->response()->withHeader("Content-Disposition", "attachment; filename=$title");
        $this->response()->sendFile($fileName);
    }

    /**
     * 测试上传到oss
     */
    public function upload()
    {
        // (new Upload())->upload(File::join(STORAGE_ROOT, 'data/0cff3e83-27b0-da63-73b2-601a94bfb1fb/object.zip'), '0cff3e83-27b0-da63-73b2-601a94bfb1fb');
    }

    public function notify()
    {
        $this->return(['type' => 'notify', 'url' => $this->params('download_url')]);
    }

    /**
     * 测试同步下载
     */
    public function testSyncDownload()
    {
        $params = [
            'source_url' => Url::assemble('/v1/test/source', 'http://localhost:9588'),
            'project_id' => 'bf1fd528-b505-baef-c19b-865f98ae6048',
            'name' => '测试任务',
            'type' => 'excel',
        ];
        $url = "http://localhost:9588/v1/download/sync?".http_build_query($params);
        $this->response()->withHeader("Content-type", "application/octet-stream");
        $this->response()->withHeader("Content-Disposition", "attachment; filename=124.xlsx");

        // $this->response()->write(file_get_contents($url));
        $this->output($url);
    }

    private function output($url, $params = [])
    {
        if ($params) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
        }
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_TIMEOUT , 60);
        // 自定义流处理函数
        curl_setopt($curl, CURLOPT_WRITEFUNCTION, array($this, 'streamingWriteCallback'));
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_exec($curl);
        curl_close($curl);
    }

    public function streamingWriteCallback($curl_handle, $data)
    {
        // $f = fopen('php://output', 'a');
        // $code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
        
        // if (intval(intval($code) / 100) != 2) {
        //     echo "err:$data";
        //     return strlen($data);
        // }

        // $length = strlen($data);
        // $written_total = 0;
        // $written_last = 0;
        
        // while ($written_total < $length) {
        //     $written_last = fwrite($f, substr($data, $written_total));

        //     if ($written_last === false) {
        //         return $written_total;
        //     }

        //     $written_total += $written_last;
        // }

        // return $written_total;
        $this->response()->write($data);
        return strlen($data);
    }

    public function testCall()
    {
        API::retrySimpleInvoke("http://localhost:9588/v1/test/timeout");
    }

    public function timeout()
    {
        Coroutine::sleep(10);
    }
}
