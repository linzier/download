# Download Center Documentation


### Objectives

The Download Center is designed to achieve the following goals:

- **Versatile**: Available for use by the merchant platform, stored-value card backend, or even third-party consumers;
- Supports both **synchronous** and **asynchronous** download modes. Synchronous mode is used for small data files, while asynchronous mode handles large data files;
- Supports **CSV** and **Excel** format downloads;
- Complex Excel formats can be customized via Excel template configuration;
- **Security**: Related API requests require authentication;
- Reasonable feedback mechanism with three notification approaches:
    - **Asynchronous callback**: The caller provides a callback URL when submitting a task. The Download Center notifies the caller when the task completes. This is typically used when the caller is a backend process;
    - **WebSocket query**: After submitting a task, the caller establishes a WebSocket connection to the Download Center. The Download Center notifies the WebSocket client upon task completion;
    - **Passive query**: Query task (list) status by project_id and task_id;
- Supports both **frontend** (browser) and **backend** (server-side) data download modes. Frontend downloads use temporary download URLs provided by the Download Center, while backend downloads use permanent URLs after authentication;

### Download Approaches

#### Asynchronous Download:
**Scenario**: When the data to be downloaded may be large, synchronous download mode may cause browser request timeouts. In such cases, asynchronous download mode is recommended.
Two sub-scenarios are discussed here: user (browser) download and system download.

**User asynchronous download in the browser:**

![User asynchronous download in the browser](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/async_browser.png)

**Description:**

1. The user clicks the "Download" button to trigger the download;
2. The browser sends a download request to the business backend;
3. The business backend assembles the request data and submits a download task to the Download Center (this API requires token authentication);
4. The Download Center creates a download task and returns the task ID to the business backend;
5. The business backend returns the task ID to the browser;
6. The browser informs the user that the download is in progress and will notify them upon completion (or directs them to check the download result later -- for cases where no WebSocket connection is established);
7. The Download Center processes the download task asynchronously (fetching data from the data source, parsing templates, generating target files, etc.);
8. Upon task completion, the Download Center notifies the WebSocket client (if connected), or the browser polls for status, or the user manually refreshes to check the task status;
9. The browser detects that the task is complete and requests a temporary download URL from the business backend (since generating the download URL requires token authentication, it must be requested by the backend);
10. The business backend requests the temporary download URL from the Download Center;
11. The Download Center returns the temporary download URL to the business backend, valid for 5 minutes;
12. The business backend returns the temporary download URL to the frontend;
13. The frontend uses the temporary download URL to download the data;

**Notes:**

- *Why does the frontend (browser) download data via a temporary URL instead of having the business backend download the data directly from the Download Center and return it to the frontend?*

  The reason is that the data to be downloaded may be very large (potentially hundreds of megabytes). If the business backend were to download the data directly, it would need to implement logic for handling large data downloads (via temporary local file transfer). Otherwise, loading all data into memory would cause memory overflow. Having the browser interact directly with the Download Center via a temporary URL eliminates the complexity on the business backend side (although if the business backend truly wants to download directly, it can also use the permanent download URL).

- *Can the browser skip establishing a WebSocket connection?*

  Yes. The browser can use either an active or passive approach. In the active approach, the browser establishes a WebSocket connection with the Download Center, and the Download Center proactively notifies the browser to perform the download upon completion.

  In the passive approach, the browser does not establish a WebSocket connection. Instead, it tells the user where to check the download result later.

  Regardless of which approach is used, it is recommended to have a unified location where download task results can be viewed. In the active approach, the WebSocket connection may break (e.g., if the page is closed), or the task may take a long time due to large data volume, causing the user to close and reopen the browser -- in all cases, the user should still be able to retrieve the downloaded data.

- In this mode, the business backend needs to provide three APIs -- two for the frontend and one for the Download Center:

  - Data download request (task submission) API, for the frontend;
  - Get temporary download URL API, for the frontend;
  - Paginated source data API, for the Download Center;

**Backend system (program) asynchronous download:**

![Backend system asynchronous download](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/async_sys.png)

**Description:**

This mode is typically used for batch data synchronization between systems, generally producing CSV files. It can also be used to provide data synchronization functionality for third parties.

1. The business system (backend program) submits a download task with a callback parameter in the request;
2. The Download Center creates a download task and returns a task ID (which the business system can save or ignore);
3. The Download Center processes the download task asynchronously;
4. Upon task completion, the Download Center notifies the business system via the callback URL that the task is complete and the data is ready for download;
5. The business system downloads the data from the Download Center;

**Notes:**

In this mode, the business backend needs to consider data volume. If the generated file is large, it cannot be loaded entirely into memory and must be written to a temporary file to prevent memory overflow.



#### Synchronous Download:

When the data volume is known to be small, synchronous download mode can be used.

![Synchronous download](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/sync_download.png)

**Description:**

This mode only requires calling a single API, making it simple to implement. It is generally used to simplify business backend download logic (generating Excel files that meet format requirements).

**Note:** If you cannot guarantee that the data volume will not grow uncontrollably in the future, do not use this mode to avoid crashes.



### Format Specifications

**Core APIs:**

1. Submit task (asynchronous download): POST /v1/task. Requires authentication.

   **Parameters:**

   - **source_url** string, optional. Data source URL. The Download Center pulls data from this URL in a loop (GET request, with page, page_size, _task_id parameters, page starts from 0).
   - **source_data** json string, optional. Either source_data or source_url must be provided, with source_data taking priority. Source data is provided at task submission time for scenarios with minimal source data, eliminating the need for an additional data API. Note: In **multi-table mode**, only source_data is supported for passing data.
   - **project_id** string, required. Project ID assigned by the Download Center.
   - **name** string, required. Task name.
   - **file_name** string, optional. Name of the downloaded file. Defaults to a date-based name with random numbers.
   - **type** string, optional. Allowed values: csv|excel. Default: csv.
   - **callback** string, optional. Callback notification URL upon completion.
   - **step** int, optional. Number of records to fetch per request (step size). Default: 1000. Range: 100 - 5000.
   - **operator_id** string, optional. Operator ID, for reference purposes.
   - **merchant_type** int, required. Merchant type: 0 = platform, 1 = single site, 2 = group, 3 = station group
   - **merchant_id** int, required. Merchant ID
   - **template** json string, optional. Header format definition. Only applicable to Excel, in JSON format. See detailed description below. The template can also be dynamically provided in the data returned by source_url.
   - **title** string|json string, optional. Spreadsheet title. Only applicable to Excel.
   - **summary** string|json string, optional. Spreadsheet summary. Only applicable to Excel.
   - **header** json string, optional. Excel header, e.g., `{"Station": "Diaoyudao", "Date": "2020-07-24"}`. Can also be dynamically provided in the data returned by source_url.
   - **footer** json string, optional. Excel footer, e.g., `{"Manager": "linvanda", "Signature": "        "}`. Can also be dynamically provided in the data returned by source_url.
   - **default_width** int, optional. Column width in points (pt). Only applicable to Excel.
   - **default_height** int, optional. Row height in points (pt). Only applicable to Excel.
   - **max_exec_time** int, optional. Task processing time limit (tasks still "in progress" beyond this limit will be re-queued for processing), in seconds. Default: 3600.

2. Synchronous download: GET /v1/download/sync. Requires token authentication.

   **Parameters:** Same as the task submission API.

CSV file format is straightforward. The focus here is on the Excel format.

**Excel template format:**

Excel templates come in two modes: **single-table mode** (default) and **multi-table mode**.
- **Single-table**: One table per Excel page;
- **Multi-table**: Multiple tables per Excel page;

*In multi-table mode, the source_data, template, title, summary, header, and footer parameters in the task submission API are essentially the single-table mode parameters wrapped in an array (list). For example, in single-table mode, title is "Table Title 1", while in multi-table mode, it would be ["Table Title 1", "Table Title 2"].*

A complete **single-table** Excel format is as follows:

![Single-table Excel template](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/excel_tpl.png)


A complete **multi-table** Excel format is as follows (essentially a repetition of the single-table mode):

![Multi-table Excel template](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/multi_table.png)


**template data format:**

The template parameter defines the column headers (col head) and row headers (row head) in the Excel file, represented as a JSON string of a Map type. It can take the following three formats (examples below use single-table mode, shown as JavaScript object literals; convert to your language's Map format accordingly):

- No template provided. In this case, the keys from the source data are used as column titles:

  ![no tpl](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/no_tpl.png)

- Simplest template:

  ```javascript
  {
    name: "Name",
    age: "Age",
    sex: "Gender",
    ...
  }
  ```

  Equivalent to:

  ```javascript
  [
  	{
  			name: "name",
  			title: "Name"
  	},
  	{
  			name: "age",
  			title: "Age"
  	},
  	...
  ]
  ```

  Also equivalent to:

  ```javascript
  {
  	col: [
        {
            name: "name",
            title: "Name"
        },
        {
            name: "age",
            title: "Age"
        },
        ...
    ]
  }
  ```

  As shown:

  ![simple tpl](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/simple_tpl.png)

- Complex column headers. Multi-level nested Maps (detailed format explained below):

  ```javascript
  [
      {
          title: "Person",
          children: [
              {
                  title: "Name",
                  name: "name"
              },
              {
                  title: "Other",
                  children: [
                      {
                          title: "Age",
                          name: "age"
                      },
                      {
                          title: "Gender",
                          name: "sex"
                      },
                      {
                          title: "Hobbies",
                          children: [
                              ...
                          ]
                      }
                  ]
              }
          ],
      },
      ...
  ]
  // Leaf nodes (those without children) are bound to data columns. Each leaf node corresponds to one column of data. Non-leaf nodes do not need a name attribute (name is used to bind data columns)
  ```

  As shown:

  ![Complex column headers](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/complex_col.png)

- Both column headers and row headers:

  ```javascript
  {
      col: [
              {
                  title: "Person",
                  children: [
                      {
                          title: "Name",
                          name: "name"
                      },
                      {
                          title: "Other",
                          children: [
                              {
                                  title: "Age",
                                  name: "age"
                              }
                              ...
                          ]
                      }
                  ],
              },
              ...
          ],
      row: [
          {
              title: "Cloud R&D",
              children: [
                  {
                      name: "front_end",
                      title: "Frontend",
                      row_count: 2
                  },
                  {
                      name: "back_end",
                      title: "Backend",
                      row_count: 4
                  }
              ]
          },
          {
              title: "OS & Smart Devices",
              ...
          }
      ]
  }
  // Row leaf nodes are bound to data rows (via the name attribute). The row_count of a leaf node indicates how many data rows that row header spans. Default is 1. Non-leaf nodes do not need a name attribute
  ```

  As shown:

  ![Both column and row headers](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/rc.png)

**Source data format:**

Source data is used to populate the data area of the Excel file, provided by the data field in the source_url response (or by source_data -- the data field in the source_url response and source_data share the same format).

Complete response format:

```javascript
{
    status: 200,
    msg: "",
    data: {
        data: [...],// Data, required
        total: 10000,// Total number of records, required
        header: {"Station": "Diaoyudao", "Date": "2020-07-24"},// Dynamically set header, optional. Can be provided at task submission time
        footer: {"Manager": "linvanda", "Signature": "        "},// Dynamically set footer, optional. Can be provided at task submission time
        template:[...] // Dynamically set Excel template, optional
    }
}
```

Inner data format:

**Data format with column headers only:**

```javascript
[
    {
        name: "Zhang San",
        age: 12,
        sex: "Male",
        love_in: "Basketball",
        ...
    },
    ...
]
// Keys (name, age, ...) correspond to the name values of leaf nodes in the column template
```

**Data format with both row headers and column headers:**

Two formats are supported:

Two-dimensional format:

```javascript
[
    {
        name: "Zhang San",
        age: 12,
        sex: "Male",
        love_in: "Basketball",
        _row_head_: "front_end"
        ...
    },
    ...
]
// Two-dimensional array format: with _row_head_, whose value corresponds to the name of a leaf node in the row template (row)
```

Three-dimensional format:

```javascript
{
    front_end: [
        {
            name: "Zhang San",
            age: 12,
            sex: "Male",
            love_in: "Basketball",
            _row_head_: "os"
            ...
        },
        ...
    ],
    back_end: [
        {
            name: "Zhang San",
            age: 12,
            sex: "Male",
            love_in: "Basketball",
            _row_head_: "os"
            ...
        },
        ...
    ],
    ...
}
// Three-dimensional format: uses the name values of leaf nodes in the row template (row) as first-level keys
```



### Technical Architecture

![Technical architecture diagram](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/arch.png)

**Description:**

The entire system consists of three main components: the application layer, the domain layer, and the monitoring (defender) processes/coroutines.

- Application layer:
  - Primarily responsible for submitting tasks to the message queue, fetching data from the queue and executing tasks, and workflow scheduling;
  - The various operations of the application layer are coordinated through a global task manager per process. The task manager launches a workflow engine in a new coroutine for each task to be processed and destroys the workflow engine when the task completes;
  - The workflow engine is responsible for scheduling the execution of workflow nodes, which invoke domain layer services to perform corresponding business logic;
- Domain layer: This layer handles specific business logic execution, such as fetching source data, parsing templates, generating target files, etc.;
- Defender processes/coroutines: Responsible for queue health monitoring, task failure re-queuing, data archiving, file cleanup, WebSocket communication, etc. Queue monitoring, task retry, and data archiving only run on the master server (the master server is specified in the Apollo configuration);



**Technical implementation of row headers and column headers:**

Row headers and column headers are essentially the same in their technical implementation. The following uses row headers as an example.

Excel row headers have a tree structure:

![tree](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/col_tree.png)

Each node maps to a row/column position in Excel:

- The depth of each node corresponds to its row number in Excel;
- The root node's row/column index is (0,1);
- The first child node at each level shares the same column number as its parent;
- The column offset of subsequent child nodes relative to their parent equals the sum of the widths of all preceding sibling nodes (a node's width equals the number of leaf nodes in its subtree; a leaf node's width is 1 -- note: this refers to column headers; for row headers, a leaf node's width is specially handled to equal its spanned row count);

The number of rows/columns a node needs to merge:

- Non-leaf nodes only need column merging, not row merging; leaf nodes only need row merging, not column merging;
- The number of columns to merge for a non-leaf node equals the width of its subtree;
- The number of rows to merge for a leaf node equals the difference between the tree's maximum depth and the leaf node's depth;


### Server Health Monitoring:
The server launches a Defender process to perform health monitoring and data cleanup, including queue health monitoring, failed task retry, database historical data archiving, and unused directory and file cleanup.
