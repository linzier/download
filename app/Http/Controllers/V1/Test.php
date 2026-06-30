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

        // Use column cache
        $cache = new MyCustomPsr16Implementation();
        \PhpOffice\PhpSpreadsheet\Settings::setCache($cache);

        // Set language
        $locale = 'pt_br';
        $validLocale = \PhpOffice\PhpSpreadsheet\Settings::setLocale($locale);
        if (!$validLocale) {
            echo 'Unable to set locale to ' . $locale . " - reverting to en_us" . PHP_EOL;
        }

        // Get specified worksheet
        $spreadsheet->getSheet(1);
        $spreadsheet->getSheetByName('Worksheet 1');
        $spreadsheet->getActiveSheet();
        $spreadsheet->getSheetCount();
        $spreadsheet->getSheetNames();
        $spreadsheet->setActiveSheetIndex(1);
        $spreadsheet->setActiveSheetIndexByName('name');

        // Get cell by index, index starts from 1
        $spreadsheet->getActiveSheet()->getCellByColumnAndRow(2, 5)->getValue();
        $letter = Coordinate::stringFromColumnIndex(2);

        // Set cell values via array
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

        // Create worksheet
        $spreadsheet->createSheet();
        // Create a new worksheet called "My Data"
        $myWorkSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'My Data');
        // Attach the "My Data" worksheet as the first worksheet in the Spreadsheet object
        $spreadsheet->addSheet($myWorkSheet, 0);

        $clonedWorksheet = clone $spreadsheet->getSheetByName('Worksheet 1');
        $clonedWorksheet->setTitle('Copy of Worksheet 1');
        $spreadsheet->addSheet($clonedWorksheet);

        // Delete worksheet
        $sheetIndex = $spreadsheet->getIndex(
            $spreadsheet->getSheetByName('Worksheet 1')
        );
        $spreadsheet->removeSheetByIndex($sheetIndex);

        // Set metadata
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

        // Set text wrapping
        $spreadsheet->getActiveSheet()->getCell('A1')->setValue("hello\nworld");
        $spreadsheet->getActiveSheet()->getStyle('A1')->getAlignment()->setWrapText(true);

        // Set cell format explicitly
        $spreadsheet->getActiveSheet()->getCell('A1')
        ->setValueExplicit(
            '25',
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
        );

        // Set hyperlink
        $spreadsheet->getActiveSheet()->setCellValue('E26', 'www.phpexcel.net');
        $spreadsheet->getActiveSheet()->getCell('E26')->getHyperlink()->setUrl('https://www.example.com');

        // Link to another worksheet
        $spreadsheet->getActiveSheet()->setCellValue('E26', 'www.phpexcel.net');
        $spreadsheet->getActiveSheet()->getCell('E26')->getHyperlink()->setUrl("sheet://'Sheetname'!A1");

        // Set print format (orientation, paper size)
            $spreadsheet->getActiveSheet()->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $spreadsheet->getActiveSheet()->getPageSetup()
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

        // Set print format: fit to width/height
        $spreadsheet->getActiveSheet()->getPageSetup()->setFitToWidth(1);
        $spreadsheet->getActiveSheet()->getPageSetup()->setFitToHeight(0);
        $spreadsheet->getActiveSheet()->getPageSetup()->setScale(100);

        // Set print format: page margins
        $spreadsheet->getActiveSheet()->getPageMargins()->setTop(1);
        $spreadsheet->getActiveSheet()->getPageMargins()->setRight(0.75);
        $spreadsheet->getActiveSheet()->getPageMargins()->setLeft(0.75);
        $spreadsheet->getActiveSheet()->getPageMargins()->setBottom(1);

        // Set print format: centering
        $spreadsheet->getActiveSheet()->getPageSetup()->setHorizontalCentered(true);
        $spreadsheet->getActiveSheet()->getPageSetup()->setVerticalCentered(false);

        // Print format: header and footer
            $spreadsheet->getActiveSheet()->getHeaderFooter()
            ->setOddHeader('&C&HPlease treat this document as confidential!');
        $spreadsheet->getActiveSheet()->getHeaderFooter()
            ->setOddFooter('&L&B' . $spreadsheet->getProperties()->getTitle() . '&RPage &P of &N');

        // Print format: set print area
        $spreadsheet->getActiveSheet()->getPageSetup()->setPrintArea('A1:E5');

        // Set cell style
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
        // Set multiple cell styles (recommended)
        $spreadsheet->getActiveSheet()->getStyle('B3:B7')->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFFF0000');
        // Set via array (better performance for many styles)
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
        // Set default styles
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial');
        $spreadsheet->getDefaultStyle()->getFont()->setSize(8);
        $spreadsheet->getActiveSheet()->getDefaultColumnDimension()->setWidth(12);
        $spreadsheet->getActiveSheet()->getDefaultRowDimension()->setRowHeight(15);

        // Conditional styles
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

        // Reuse styles
        $spreadsheet->getActiveSheet()
        ->duplicateStyle(
            $spreadsheet->getActiveSheet()->getStyle('B2'),
            'B3:B7'
        );

        // Set column filter
        $spreadsheet->getActiveSheet()->setAutoFilter('A1:C9');

        // Set column width
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(12);
        $spreadsheet->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);// Auto width

        // Set row height
        $spreadsheet->getActiveSheet()->getRowDimension('10')->setRowHeight(100);// Default is 12.75 pts

        // Merge cells
        $spreadsheet->getActiveSheet()->mergeCells('A18:E22');

        // Add image
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


        // Data formatting
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
                'name' => 'Zhang San',
                'age' => mt_rand(10, 100).'',
                'sex' => 'Male',
                'love_in' => 'Table Tennis',
                'love_out_land' => 'Running',
                'love_out_sky' => 'Skydiving',
                'city' => ['Shenzhen', 'Shanghai'][mt_rand(0,1)],
                'area' => 'Area Name',
                'building' => 'Community',
                '_row_head_' => 'front_end',
            ],
            [
                'name' => 'Zhang Si',
                'age' => mt_rand(10, 100),
                'sex' => 'Male',
                'love_in' => 'Table Tennis',
                'love_out_land' => 'Running',
                'love_out_sky' => 'Skydiving',
                'city' => ['Shenzhen', 'Shanghai'][mt_rand(0,1)],
                'area' => 'Area Name',
                'building' => 'Community',
                '_row_head_' => 'front_end',
            ],
            [
                'name' => 'Zhang Wu',
                'age' => mt_rand(10, 100),
                'sex' => 'Male',
                'love_in' => 'Table Tennis',
                'love_out_land' => 'Running',
                'love_out_sky' => 'Skydiving',
                'city' => ['Shenzhen', 'Shanghai'][mt_rand(0,1)],
                'area' => 'Area Name',
                'building' => 'Community',
                '_row_head_' => 'back_end',
            ],
            [
                'name' => 'Zhang Liu',
                'age' => mt_rand(10, 100),
                'sex' => 'Male',
                'love_in' => 'Table Tennis',
                'love_out_land' => 'Running',
                'love_out_sky' => 'Skydiving',
                'city' => ['Shenzhen', 'Shanghai'][mt_rand(0,1)],
                'area' => 'Area Name',
                'building' => 'Community',
                '_row_head_' => 'back_end',
            ],
            [
                'name' => 'Zhang Qi',
                'age' => mt_rand(10, 100),
                'sex' => 'Male',
                'love_in' => 'Table Tennis',
                'love_out_land' => 'Running',
                'love_out_sky' => 'Skydiving',
                'city' => ['Shenzhen', 'Shanghai'][mt_rand(0,1)],
                'area' => 'Area Name',
                'building' => 'Community',
                '_row_head_' => 'back_end',
            ],
            [
                'name' => 'Zhang Ba',
                'age' => mt_rand(10, 100),
                'sex' => 'Male',
                'love_in' => 'Table Tennis',
                'love_out_land' => 'Running',
                'love_out_sky' => 'Skydiving',
                'city' => ['Shenzhen', 'Shanghai'][mt_rand(0,1)],
                'area' => 'Area Name',
                'building' => 'Community',
                '_row_head_' => 'back_end',
            ],
            [
                'name' => 'Zhang Jiu',
                'age' => mt_rand(10, 100),
                'sex' => 'Male',
                'love_in' => 'Table Tennis',
                'love_out_land' => 'Running',
                'love_out_sky' => 'Skydiving',
                'city' => ['Shenzhen', 'Shanghai'][mt_rand(0,1)],
                'area' => 'Area Name',
                'building' => 'Community',
                '_row_head_' => 'os',
            ],
            [
                'name' => 'Zhang Shi',
                'age' => mt_rand(10, 100),
                'sex' => 'Male',
                'love_in' => 'Table Tennis',
                'love_out_land' => 'Running',
                'love_out_sky' => 'Skydiving',
                'city' => ['Shenzhen', 'Shanghai'][mt_rand(0,1)],
                'area' => 'Area Name',
                'building' => 'Community',
                '_row_head_' => 'os',
            ],
            [
                'name' => 'Zhang Shiyi',
                'age' => mt_rand(10, 100),
                'sex' => 'Male',
                'love_in' => 'Table Tennis',
                'love_out_land' => 'Running',
                'love_out_sky' => 'Skydiving',
                'city' => ['Shenzhen', 'Shanghai'][mt_rand(0,1)],
                'area' => 'Area Name',
                'building' => 'Community',
                '_row_head_' => 'os',
            ],
            [
                'name' => 'Zhang Shier',
                'age' => mt_rand(10, 100),
                'sex' => 'Male',
                'love_in' => 'Table Tennis',
                'love_out_land' => 'Running',
                'love_out_sky' => 'Skydiving',
                'city' => ['Shenzhen', 'Shanghai'][mt_rand(0,1)],
                'area' => 'Area Name',
                'building' => 'Community',
                '_row_head_' => 'pos',
            ],
            [
                'name' => 'Zhang Shisan',
                'age' => mt_rand(10, 100),
                'sex' => 'Male',
                'love_in' => 'Table Tennis',
                'love_out_land' => 'Running',
                'love_out_sky' => 'Skydiving',
                'city' => ['Shenzhen', 'Shanghai'][mt_rand(0,1)],
                'area' => 'Area Name',
                'building' => 'Community',
                '_row_head_' => 'pos',
            ],
            [
                'name' => 'Zhang Shisi',
                'age' => mt_rand(10, 100),
                'sex' => 'Male',
                'love_in' => 'Table Tennis',
                'love_out_land' => 'Running',
                'love_out_sky' => 'Skydiving',
                'city' => ['Shenzhen', 'Shanghai'][mt_rand(0,1)],
                'area' => 'Area Name',
                'building' => 'Community',
                '_row_head_' => 'screen',
            ],
            [
                'name' => 'Zhang Shiwu',
                'age' => mt_rand(10, 100),
                'sex' => 'Male',
                'love_in' => 'Table Tennis',
                'love_out_land' => 'Running',
                'love_out_sky' => 'Skydiving',
                'city' => ['Shenzhen', 'Shanghai'][mt_rand(0,1)],
                'area' => 'Area Name',
                'building' => 'Community',
                '_row_head_' => 'screen',
            ],
            [
                'name' => 'Zhang Shiliu',
                'age' => 12345678901,
                'sex' => 'Male',
                'love_in' => 'Table Tennis',
                'love_out_land' => 'Running',
                'love_out_sky' => 'Skydiving',
                'city' => ['Shenzhen', 'Shanghai'][mt_rand(0,1)],
                'area' => 'Area Name',
                'building' => 'Community',
                '_row_head_' => 'screen',
            ],
        ];
        $page = $this->params('page') ?: 0;
        // $data = [];
        // for ($i = 0; $i < 1000; $i++) {
        //     $data[] = [
        //         'name' => "Zhang San{$i}-{$page}",
        //         'age' => mt_rand(10, 100),
        //         'sex' => ['Male', 'Female'][mt_rand(0,1)],
        //         'love_in' => ['Table Tennis', 'Badminton'][mt_rand(0,1)],
        //         'love_out_land' => ['Running', 'Hiking'][mt_rand(0,1)],
        //         'love_out_sky' => 'Skydiving',
        //         'city' => ['Shenzhen', 'Shanghai'][mt_rand(0,1)],
        //         'area' => 'Area Name',
        //         'building' => 'Community'
        //     ];
        // }
//        $force = mt_rand(0, 10);

        $this->return([
             'data' => $data,
//                'force_continue' => $force,
//            "data" => [
//                [
//                    "name" => 'An',
//                    "age" => 43
//                ]
//            ],
//            'data' => [],
//            'total' => 0,
            // 'header' => ["Station" => 'Diaoyudao', 'Date' => date('Y-m-d')],
            // 'footer' => ['Manager' => 'Songlin', 'Director Signature' => '', 'CEO Signature' => ''],
//            'template' => ['name' => 'Name', 'sex' => 'Gender']
//             'template' => [
//                 'row' => [
//                     [
//                         'title' => 'Cloud R&D',
//                         'children' => [
//                             [
//                                 'name' => 'front_end',
//                                 'title' => 'Frontend',
//                                 'row_count' => 2,
//                             ],
//                             [
//                                 'name' => 'back_end',
//                                 'title' => 'Backend',
//                                 'row_count' => 4,
//                             ],
//                         ]
//                     ],
//                     [
//                         'title' => 'OS & Smart Devices',
//                         'children' => [
//                             [
//                                 'title' => 'OS',
//                                 'name' => 'os',
//                                 'row_count' => 3,
//                             ],
//                             [
//                                 'title' => 'Smart Devices',
//                                 'children' => [
//                                     [
//                                         'name' => 'pos',
//                                         'title' => 'Handheld Terminal',
//                                         'row_count' => 2,
//                                     ],
//                                     [
//                                         'name' => 'screen',
//                                         'title' => 'Large Screen',
//                                         'row_count' => 3,
//                                     ],
//                                 ]
//                             ]
//                         ]
//                     ]
//                 ],
//                 'col' => [
//                     [
//                         'title' => 'Person',
//                         'children' => [
//                             [
//                                 'name' => 'name',
//                                 'title' => 'Name',
//                                 'type' => 'string',
//                                 'color' => 'red',
//                                 "width" => -1
//                             ],
//                             [
//                                 'title' => 'Other',
//                                 'children' => [
//                                     [
//                                         'name' => 'age',
//                                         'title' => 'Age',
//                                         'type' => 'number',
//                                     ],
//                                     [
//                                         'name' => 'sex',
//                                         'title' => 'Gender',
//                                         'type' => 'string',
//                                         'width' => 8,
//                                     ],
//                                     [
//                                         'title' => 'Hobbies',
//                                         'children' => [
//                                             [
//                                                 'name' => 'love_in',
//                                                 'title' => 'Indoor',
//                                             ],
//                                             [
//                                                 'title' => 'Outdoor',
//                                                 'children' => [
//                                                     [
//                                                         'name' => 'love_out_land',
//                                                         'title' => 'Land',
//                                                     ],
//                                                     [
//                                                         'name' => 'love_out_sky',
//                                                         'title' => 'Sky',
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
//                         'title' => 'Address',
//                         'children' => [
//                             [
//                                 'name' => 'city',
//                                 'title' => 'City'
//                             ],
//                             [
//                                 'title' => 'Community',
//                                 'children' => [
//                                     [
//                                         'name' => 'area',
//                                         'title' => 'Area',
//                                     ],
//                                     [
//                                         'name' => 'building',
//                                         'title' => 'Building',
//                                     ]
//                                 ]
//                             ]
//                         ]
//                     ],
//                 ],
//                 'col_' => [
//                     'name' => 'Name',
//                     'age' => 'Age',
//                     'sex' => 'Gender',
//                     'love_in' => 'Indoor Hobby',
//                     'love_out_land' => 'Outdoor Land Hobby',
//                     'love_out_sky' => 'Outdoor Sky Hobby',
//                     'city' => 'City',
//                     'area' => 'Area',
//                     'building' => 'Community',
//                 ]
//             ]
        ]);
    }

    /**
     * Test creating large file
     */
    public function createBigFile()
    {
        set_time_limit(0);
        $f = fopen(File::join(STORAGE_ROOT, "temp/big_file.csv"), 'w');
        
        for ($i = 0; $i < 6000000; $i++) {
            fputcsv($f, ["Zhang Songlin","Zhang Songlin","Zhang Songlin","Zhang Songlin","Zhang Songlin","Zhang Songlin","Zhang Songlin","Zhang Songlin","Zhang Songlin","Zhang Songlin","Zhang Songlin","Zhang Songlin","Zhang Songlin",]);
        }
        fclose($f);
    }

    /**
     * Test downloading large file
     */
    public function download()
    {
        set_time_limit(0);
        $fileName = File::join(STORAGE_ROOT, "temp/big_file.csv");
        $title = "test_large_file.csv";
        $this->response()->withHeader("Content-Disposition", "attachment; filename=$title");
        $this->response()->sendFile($fileName);
    }

    /**
     * Test upload to OSS
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
     * Test synchronous download
     */
    public function testSyncDownload()
    {
        $params = [
            'source_url' => Url::assemble('/v1/test/source', 'http://localhost:9588'),
            'project_id' => 'bf1fd528-b505-baef-c19b-865f98ae6048',
            'name' => 'Test Task',
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
        // Custom stream handler
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
