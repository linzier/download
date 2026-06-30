<?php

namespace App;

use WecarSwoole\ErrCode as BaseErrCode;

/**
 * Class ErrCode
 * 200 means OK
 * 500 and below are reserved by the framework. Do not use in this project. Project error codes start from 501.
 * @package App
 */
class ErrCode extends BaseErrCode
{
    public const EMPTY_PARAMS = 501; // Missing parameters
    public const PROJ_NOT_EXISTS = 502; // Project does not exist
    public const PROJ_AREADY_EXISTS = 503; // Project already exists
    public const GROUP_NOT_EXISTS = 504; // Project group does not exist
    public const GROUP_AREADY_EXISTS = 505; // Project group already exists
    public const FILE_TYPE_ERR = 506; // Invalid file type
    public const TASK_NOT_EXISTS = 507; // Task does not exist
    public const TPL_FMT_ERR = 508; // Invalid template format
    public const INVALID_STATUS_OP = 509; // Invalid status transition
    public const FETCH_SOURCE_FAILED = 510; // Failed to fetch source data
    public const FILE_OP_FAILED = 511; // File operation failed
    public const SOURCE_DATA_EMPTY = 512;
    public const DOWNLOAD_FAILED = 513;
    public const SOURCE_TYPE_ERR = 514;
    public const DATA_FORMAT_ERR = 515; // Data format error
    public const SOURCE_FORMAT_ERR = 516;
}
