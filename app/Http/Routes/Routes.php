<?php

namespace App\Http\Routes;

use WecarSwoole\Http\Route;

class Routes extends Route
{
    public function map()
    {
        // -------- For testing --------
        $this->get("/v1/test", "/V1/Test/index");
        $this->get("/v1/test/download", "/V1/Test/download");
        $this->get("/v1/test/create", "/V1/Test/createBigFile");
        $this->get("/v1/test/source", "/V1/Test/sourceData");
        $this->get("/v1/test/upload", "/V1/Test/upload");
        $this->get("/v1/test/notify", "/V1/Test/notify");
        $this->get('/v1/test/sync', '/V1/Test/testSyncDownload');
        $this->get('/v1/test/retry', '/V1/Test/testCall');
        $this->get('/v1/test/timeout', '/V1/Test/timeout');
        // -------- End testing --------

        /**
         * Download data
         * @params:
         *      ticket string required. Temporary ticket, valid for 10 minutes
         */
        $this->get('/v1/download', '/V1/Download/getData');
    }
}
