<?php

namespace App\Http\Routes;

use WecarSwoole\Http\ApiRoute;

class AuthAPI extends ApiRoute
{
    public function map()
    {
        /**
         * Create project
         *  param:
         *      name string required. Project name. Must be unique
         *      group_id string required. Project group
         *  return:
         *      project_id string Project ID
         */
        $this->post('/v1/project', '/V1/Project/createProject');

        /**
         * Create project group
         * param:
         *      name string required. Name, must be unique
         * return:
         *      group_id string Project group ID
         */
        $this->post('/v1/group', '/V1/Project/createGroup');
        
        /**
         * Submit task
         * param:
         *      source_url string, optional. Data source URL
         *      source_data json string, optional. Either source_url or source_data is required. For small datasets, data can be provided directly via source_data (format same as data returned by source_url)
         *      project_id string required. Project ID assigned by Download Center
         *      name string required. Task name
         *      file_name string optional. Download file name, defaults to date-based name with random numbers
         *      type string optional. Allowed values: csv|excel. Default: csv
         *      callback string optional. Callback notification URL upon completion
         *      step int optional. Number of records to fetch per request (step size). Default: 1000. Range: 100 - 5000
         *      operator_id string optional. Operator ID, for reference purposes
         *      template string optional. Header format definition. Only applicable to Excel
         *      title string optional. Spreadsheet title. Only applicable to Excel
         *      summary string optional. Spreadsheet summary. Only applicable to Excel
         *      header array optional. Spreadsheet header. Only applicable to Excel
         *      footer array optional. Spreadsheet footer. Only applicable to Excel
         *      header_align string optional. Header alignment
         *      footer_align string optional. Footer alignment
         *      col_align string optional. Table column alignment. Can be overridden by col align set in template
         *      default_width int optional. Column width in pt. Only applicable to Excel
         *      default_height int optional. Row height in pt. Only applicable to Excel
         *      max_exec_time int optional. Task processing time limit in seconds (tasks still "in progress" beyond this limit will be re-queued)
         *      interval int optional. Interval in milliseconds between two data fetches. Range: 100 ~ 3000, default: 100
         *      rowoffset int optional. Row offset, which row to start rendering the first table from. Default: 0 (no offset)
         */
        $this->post("/v1/task", "/V1/Task/deliver");

        /**
         * Submit task: multi-table mode.
         * Currently only supports Excel.
         * Supports generating multiple tables in one tab page, or multiple tabs in one Excel file with one table per tab (not yet implemented)
         * Parameters are basically the same as /v1/task single-table mode, except related parameters wrap the single-table parameters in an array (source_data, template, title, summary, header, footer, and source_url response body)
         * param:
         *      project_id string required. Project ID assigned by Download Center
         *      name string required. Task name
         *      file_name string optional. Download file name, defaults to date-based name with random numbers
         *      type string optional. Default: excel (currently only supports excel)
         *      multi_type string optional. Multi-table mode: page|tab. Page mode (default): multiple tables on one page; Tab mode: multiple tabs with one table per tab (not yet implemented)
         *      callback string optional. Callback notification URL upon completion
         *      operator_id string optional. Operator ID, for reference purposes
         *      source_data json string, source data. Multi-table mode must provide source data via source_data
         *      template string optional. Header format definition. Only applicable to Excel
         *      title string optional. Spreadsheet title. Only applicable to Excel
         *      summary string optional. Spreadsheet summary. Only applicable to Excel
         *      header array optional. Spreadsheet header. Only applicable to Excel
         *      footer array optional. Spreadsheet footer. Only applicable to Excel
         *      default_width int optional. Column width in pt. Only applicable to Excel
         *      default_height int optional. Row height in pt. Only applicable to Excel
         *      max_exec_time int optional. Task processing time limit in seconds (tasks still "in progress" beyond this limit will be re-queued)
         *      interval int optional. Interval in milliseconds between two data fetches. Range: 100 ~ 3000, default: 100
         *      rowoffset int optional. Row offset, which row to start rendering the first table from. Default: 0 (no offset)
         */
        $this->post("/v1/task/multiple", "/v1/Task/deliverMultiple");

        /**
         * Query task details
         * params:
         *      task_id string required. Task ID
         */
        $this->get('/v1/task', '/V1/Task/one');

        /**
         * Query task list by project
         * params:
         *      project_id string required. Project ID
         *      page int required. Page number, starting from 0
         *      page_size int required. Page size, max 200
         *      status string optional. Multiple statuses separated by commas, defaults to all statuses
         *      merchant_id int optional. Merchant ID
         *      merchant_type int optional. Merchant type
         */
        $this->get('/v1/tasks', '/V1/Task/list');

        /**
         * Delete tasks
         * params:
         *      task_ids string required. Task IDs to delete, separated by commas
         *      project_ids string required. Project IDs the tasks belong to, separated by commas
         *      operator_id string optional. Specify which operator's tasks to delete
         */
        $this->post('/v1/tasks/delete', 'V1/Task/delete');

        /**
         * Fetch data (get asynchronously generated data)
         * params:
         *      task_id string required. Task ID
         */
        $this->get('/v1/download/async', '/V1/Download/asyncGetData');

        /**
         * Get temporary download URL
         * params:
         *      task_id string required. Task ID
         */
        $this->get('/v1/download/url', '/V1/Download/getDownloadUrl');

        /**
         * Synchronous data retrieval (no need to submit task, directly call this API to generate and download target file)
         * This API only supports retrieving small amounts of data
         * Parameters are the same as POST /v1/task, except async-related parameters (e.g., callback) are not applicable
         * Supports both GET and POST methods
         */
        $this->get('/v1/download/sync', '/V1/Download/syncGetData');
        $this->post('/v1/download/sync', '/V1/Download/syncGetData');
        /**
         * Synchronous data retrieval: multi-table mode
         * This API only supports retrieving small amounts of data
         * Parameters are the same as POST /v1/task/multiple, except async-related parameters (e.g., callback) are not applicable
         * Supports both GET and POST methods
         */
        $this->get('/v1/download/sync/multiple', '/V1/Download/syncGetDataMultiple');
        $this->post('/v1/download/sync/multiple', '/V1/Download/syncGetDataMultiple');
    }
}
