<?php

namespace App\Http\Routes;

use WecarSwoole\Http\Route;

class Routes extends Route
{
    public function map()
    {
        // -------- 测试用 --------
        $this->get("/v1/test", "/V1/Test/index");
        $this->get("/v1/test/download", "/V1/Test/download");
        $this->get("/v1/test/create", "/V1/Test/createBigFile");
        $this->get("/v1/test/source", "/V1/Test/sourceData");
        $this->get("/v1/test/upload", "/V1/Test/upload");
        $this->get("/v1/test/notify", "/V1/Test/notify");
        $this->get('/v1/test/sync', '/V1/Test/testSyncDownload');
        $this->get('/v1/test/retry', '/V1/Test/testCall');
        $this->get('/v1/test/timeout', '/V1/Test/timeout');
        // -------- 测试用 End --------

        /**
         * 下载数据
         * @params:
         *      ticket string 必填。临时 ticket，十分钟有效期
         */
        $this->get('/v1/download', '/V1/Download/getData');
    }
}
