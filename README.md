# 下载中心说明文档


### 目标
下载中心需要实现如下目标：

- 通用。可供商户平台、储值卡后台、甚至是第三方使用；
- 支持**同步**和**异步**两种下载模式。同步用来下载小数据量文件，异步下载大数据量文件；
- 支持 **csv** 和 **excel** 格式的下载需求；
- 可通过配置 excel 模板定制复杂的 excel 格式；
- 安全性。相关接口请求需要鉴权；
- 合理的反馈机制。实现三种反馈机制：
    - **异步回调：**调用方提交任务时提供 callback，下载中心任务完成时通知对方，一般用于对方是后台程序下载的情况；
    - **WebSocket 查询：**调用方提交任务后建立到下载中心的 WebSocket 连接，下载中心任务完成后回复 WebSocket 客户端；
    - **被动查询：**根据 project_id、task_id 查询任务（列表）；
- 支持**前端**（浏览器）和**后端**（后台服务）两种数据下载模式。前端下载通过下载中心提供的临时下载 url 下载数据，后端则在鉴权后通过永久 url 下载数据；

### 下载方案

#### 异步下载：
**场景：**业务需要下载的数据量可能较大时，同步下载模式会导致浏览器请求超时，此时建议采用异步下载模式。
这里分别讨论用户（浏览器）下载和系统下载两种场景。

**用户在浏览器异步下载：**

![用户在浏览器异步下载资源](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/async_browser.png)

**说明：**

1. 用户点击“下载”按钮触发下载；
2. 浏览器向业务后台发出下载请求；
3. 业务后台组装请求数据，向下载中心投递下载任务（该 API 需要 token 鉴权）；
4. 下载中心生成下载任务，并返回任务编号返回给业务后台；
5. 业务后台将获取到的任务编号返回给浏览器；
6. 浏览器告知用户正在下载中，完成下载后将提示您（或者稍后请去某地方查看下载结果——针对不建立 WebSocket 连接的情况）；
7. 下载中心异步处理下载任务（从数据源拉取数据、解析模板、生成目标文件等）；
8. 下载中心任务处理完成后，通知 WebSocket 客户端（如果有），或者浏览器轮询，或者用户手动刷新查看任务状态；
9. 浏览器感知到任务处理完成，向业务后端请求临时下载地址（由于生成下载地址需要 token 鉴权，必须由后端去请求下载中心）；
10. 业务后端请求下载中心获取临时下载地址；
11. 下载中心返回临时下载 url 给业务后端，5 分钟有效期；
12. 业务后端将临时下载 url 返回给前端；
13. 前端使用临时下载 url 下载数据；

**注意：**

- *此处为何要由前端（浏览器）通过临时 url 下载数据，而不是由业务后端直接从下载中心下载数据返回给前端？*

  原因是需要下载的数据可能很大（可能有几百兆），如果由业务后端直接下载，则业务后端必须实现大数据下载的代码逻辑（通过本地临时文件中转），否则直接把数据放入内存，会导致内存溢出。由浏览器通过临时 url 直接跟下载中心交互，省去了业务后台的复杂性（当然如果业务后台自己真想直接下载，也可以调永久下载 url 去下载数据）。

- *浏览器端可以不建立 WebSocket 连接吗？*

  可以。浏览器端可以采用主动方案或者被动方案。主动方案中浏览器和下载中心建立 WebSocket 连接，下载中心处理完毕后主动通知浏览器执行下载。

  被动方案中浏览器不建立 WebSocket 连接，而是告诉用户稍后可以去哪里查看下载结果。

  无论采用主动方案还是被动方案，建议都要有一个统一的地方可以查看下载任务的处理结果，因为主动方案中， WebSocket 连接可能会断开（比如关闭了页面），或者由于数据量太大，用时较长，用户关闭了浏览器重新打开，应该仍然能够获取得到下载的数据。

- 此模式下业务后端需要提供三个 API，其中两个给前端用，一个给下载中心用：

  - 数据下载请求（任务投递）接口，给前端用；
  - 获取临时下载 url 接口，给前端用；
  - 分页获取源数据接口，给下载中心用；

**后台系统（程序）异步下载：**

![后台程序异步下载](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/async_sys.png)

**说明：**

这种模式一般用在系统间数据批量同步上，一般生成 csv 文件。也可以通过这种方式为第三方提供数据同步功能。

1. 业务系统（后端程序）投递下载任务，并在请求参数中带上 callback 参数；
2. 下载中心生成下载任务，并返回任务编号（业务系统可保存也可忽略）；
3. 下载中心异步处理下载任务；
4. 任务处理完毕，下载中心通过 callback 通知业务系统任务处理完毕，可下载了；
5. 业务系统从下载中心下载数据；

**注意：**

此模式下，业务后端需要考虑数据量问题，如果生成的文件很大，则不能一次全放入内存中，而要写入到临时文件中，防止内存溢出。



#### 同步下载：

当能预判数据量不会太大时，可以使用同步下载模式。

![同步下载](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/sync_download.png)

**说明：**

此模式只需要调一个接口，实现简单，一般用来简化业务后端下载逻辑处理（生成符合格式要求的 excel）。

**注意：**如果不能保证未来数据量不会不可控地增长，请不要用这种模式，防止出现崩溃。



### 格式说明

**核心接口：**

1. 投递任务（异步下载）：POST /v1/task。需要鉴权。

   **参数：**

   - **source_url** string 选填。数据源 url，下载中心从此 url 循环拉取数据（GET 请求，会带上 page、page_size、_task_id 参数，page 从 0 开始）。
   - **source_data** json string 选填。source_data 和 source_url 必须提供一个，以 source_data 优先。投递任务时即提供源数据，针对源数据量很少的场景，此时不需要通过额外接口提供数据。注意：**多表格模式**下仅支持 source_data 传数据。
   - **project_id** string 必填。项目 id，由下载中心分配。
   - **name** string 必填。任务名称。
   - **file_name** string 可选。下载文件的名称，默认根据日期加随机数生成。
   - **type** string 可选。可选值：csv|excel。默认 csv。
   - **callback** string 可选。处理完成后回调通知 url。
   - **step** int 可选。下载数据时每次取多少数据（步长），默认 1000，可设置范围：100 - 5000。
   - **operator_id** string 可选。操作员编号，存根用。
   - **merchant_type** int 必填。商户类型：0 平台，1 单站，2 集团，3 油站组
   - **merchant_id** int 必填。商户编号
   - **template** json string 可选。表头格式定义。仅对 excel 生效，json。详见后面说明。template 也可以在 source_url 返回的数据中动态提供。
   - **title** string|json string 可选。表格标题。仅对 excel 生效。
   - **summary** string|json string 可选。表格摘要。仅对 excel 生效。
   - **header** json string 可选。excel header，如`{"油站": "钓鱼岛", "日期": "2020-07-24"}`。可以在  source_url 返回的数据中动态提供。
   - **footer** json string 可选。excel footer，如`{"负责人": "linvanda", "签名": "        "}` 。可以在  source_url 返回的数据中动态提供。
   - **default_width** int 可选。表格列宽度，单位 pt。仅对 excel 生效。
   - **default_height** int 可选。表格行高度，单位 pt。仅对 excel 生效。
   - **max_exec_time** int 可选。任务处理时限（超过该时限还在“处理中”的任务将重新入列处理），单位秒，默认 3600。

2. 同步下载：GET /v1/download/sync。需要 token 鉴权。

   **参数：**同投递任务接口参数。
   
csv 文件格式很简单，这里重点说明下 excel 格式。

**excel 模板格式：**

excel 模板分为**单表格模式**（默认）和**多表格模式**。
- **单表格**：一页 excel 只有一个表格；
- **多表格**：一页 excel 有多个表格；

*多表格模式下，投递任务接口中的 source_data、template、title、summary、header、footer 参数等于是将单表格模式下的这些参数放入数组（列表）中，如单表格模式下 title 为"表格标题 1"，多表格模式下为 ["表格标题1", "表格标题 2"]*

一个完整的**单表格** excel 格式如下：

![单表格 excel 模板](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/excel_tpl.png)


一个完整的**多表格型** excel 格式如下（即对单表格模式的重复）：

![多表格 excel 模板](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/multi_table.png)


**template 数据格式：**

teplate 参数用来定义 excel 中列表头（col head）和行表头（row head）部分，是 Map 类型的 json 字符串表示。可以有以下三种格式(下面以单表格模式为例，使用 javascript 的字面量对象表示，其它语言请转成各自的 Map 格式)：

- 不提供模板。此时会以源数据中的 key 作为列标题：

  ![no tpl](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/no_tpl.png)

- 最简单的模板：

  ```javascript
  {
    name: "姓名",
    age: "年龄",
    sex: "性别",
    ...
  }
  ```

  等价于：

  ```javascript
  [
  	{
  			name: "name",
  			title: "姓名"
  	},
  	{
  			name: "age",
  			title: "年龄"
  	},
  	...
  ]
  ```

  还等价于：

  ```javascript
  {
  	col: [
        {
            name: "name",
            title: "姓名"
        },
        {
            name: "age",
            title: "年龄"
        },
        ...
    ]
  }
  ```

  如图：

  ![simple tpl](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/simple_tpl.png)

- 复杂格式的列表头。多层嵌套 Map（具体格式后面详解）：

  ```javascript
  [
      {
          title: "人员",
          children: [
              {
                  title: "姓名",
                  name: "name"
              },
              {
                  title: "其它",
                  children: [
                      {
                          title: "年龄",
                          name: "age"
                      },
                      {
                          title: "性别",
                          name: "sex"
                      },
                      {
                          title: "爱好",
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
  // 叶子节点（没有 children）挂接数据列，每个叶子节点对应一列数据。非叶子节点不需要 name 属性（name 是用来挂接数据列的）
  ```

  如图：

  ![复杂列表头](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/complex_col.png)

- 同时有列表头和行表头：

  ```javascript
  {
      col: [
              {
                  title: "人员",
                  children: [
                      {
                          title: "姓名",
                          name: "name"
                      },
                      {
                          title: "其它",
                          children: [
                              {
                                  title: "年龄",
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
              title: "云研发",
              children: [
                  {
                      name: "front_end",
                      title: "前端",
                      row_count: 2
                  },
                  {
                      name: "back_end",
                      title: "后端",
                      row_count: 4
                  }
              ]
          },
          {
              title: "OS及智能设备",
              ...
          }
      ]
  }
  // row 的叶子节点挂接数据行（通过 name 属性挂接），叶子节点的 row_count 表示该行表头囊括多少行数据，默认是 1。非叶子节点不需要 name 属性
  ```

  如图：

  ![同时有列表头和行表头](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/rc.png)

**源数据格式：**

源数据用来填充 excel 的数据区域，由 source_url 返回数据的 data 项提供（或者 source_data 提供，source_data 和 source_url 返回值中的 data 项相同）。

完整响应格式：

```javascript
{
    status: 200,
    msg: "",
    data: {
        data: [...],// 数据，必须
        total: 10000,// 共有多少数据，必须
        header: {"油站": "钓鱼岛", "日期": "2020-07-24"},// 动态设置 header，可选。可在任务投递时提供
        footer: {"负责人": "linvanda", "签名": "        "},// 动态设置 footer，可选。可在任务投递时提供
        template:[...] // 动态设置 excel 模板，可选
    }
}
```

内层 data 格式：

**只有列表头时的数据格式：**

```javascript
[
    {
        name: "张三",
        age: 12,
        sex: "男",
        love_in: "篮球",
        ...
    },
    ...
]
// 其中的 key(name,age,...)对应 template 中列表头叶子节点 name 的值。
```

**同时有行表头和列表头时的数据格式：**

可以有两种格式：

二维格式：

```javascript
[
    {
        name: "张三",
        age: 12,
        sex: "男",
        love_in: "篮球",
        _row_head_: "front_end"
        ...
    },
    ...
]
// 二维数组格式，加上 _row_head_，其值对应 template 中行表头（row）叶子节点的 name 的值
```

三维格式：

```javascript
{
    front_end: [
        {
            name: "张三",
            age: 12,
            sex: "男",
            love_in: "篮球",
            _row_head_: "os"
            ...
        },
        ...
    ],
    back_end: [
        {
            name: "张三",
            age: 12,
            sex: "男",
            love_in: "篮球",
            _row_head_: "os"
            ...
        },
        ...
    ],
    ...
}
// 三维格式，用 template 中行表头（row）叶子节点的 name 值作为第一维的 key
```



### 技术架构

![技术架构图](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/arch.png)

**说明：**

整个系统主要由三大块组成：应用层、领域层和监测（守卫）进程/协程。

- 应用层：
  - 主要作用是投递任务到消息队列、从消息队列取出数据并执行任务、工作流调度；
  - 应用层的各项工作主要通过进程全局的任务管理器来协调，任务管理器为每个待处理的任务在新协程中启动工作流引擎，并在任务执行结束时销毁工作流引擎；
  - 工作流引擎负责调度相应工作流节点执行，工作流节点调用领域层相关服务实现相应业务处理；
- 领域层：该层负责具体的业务逻辑执行，如获取源数据、解析模板、生成目标文件等；
- 守卫进程/协程：负责队列健康监控、任务失败重入列、数据归档、文件清理、WebSocket 通信等。其中队列监控、任务重试、数据归档只会在主服务器上执行（在 apollo 配置指定哪台是主服务器）；



**行表头和列表头的技术实现：**

行表头和列表头技术实现上本质是一致的，下面以行表头为例。

excel 行表头是一颗树形结构：

![tree](https://github.com/linvanda/download/blob/705248dd961acc5acd8c79ebede4fcfd847a666b/readme/col_tree.png)

每个节点对应到 excel 上的行列位置：

- 每个节点的深度对应其在 excel 中的行号；
- 根节点的行列编号是 (0,1)；
- 每层第一个子节点的列号和父节点的相同；
- 后续子节点相对于父节点的列偏移量是前面所有邻居节点的广度之和（一个节点的广度等于该子树的叶节点数，叶节点的广度是 1——注意，这里是说列表头，行表头的叶节点的广度做了特殊处理，等于其囊括的行数）；

一个节点需要合并的行列数：

- 非叶节点只需要进行列合并，无需行合并；叶节点只需要进行行合并，无需进行列合并；
- 非叶节点需要合并的列数等于其子树的广度；
- 叶节点需要合并的行数等于该叶节点的深度于树最大深度之差；


### 服务器健康监控：
服务器会启动 Defender 进程执行一些健康监控和数据清理，包括队列健康监控、失败任务重试、数据库历史数据归档、无用目录和文件清理。
