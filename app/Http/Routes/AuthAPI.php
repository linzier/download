<?php

namespace App\Http\Routes;

use WecarSwoole\Http\ApiRoute;

class AuthAPI extends ApiRoute
{
    public function map()
    {
        /**
         * 创建项目
         *  param:
         *      name string 必填。项目名称。不可重复
         *      group_id string 必填。项目组
         *  return:
         *      project_id string 项目 id
         */
        $this->post('/v1/project', '/V1/Project/createProject');

        /**
         * 创建项目组
         * param:
         *      name string 必填。名称，不可重复
         * return:
         *      group_id string 项目组 id
         */
        $this->post('/v1/group', '/V1/Project/createGroup');
        
        /**
         * 投递任务
         * param:
         *      source_url string，可选。数据源 url
         *      source_data json string，可选。source_url 和 source_data 必须有一个。当数据量不大时可以直接通过 source_data 提供（格式同 source_url 返回的 data)
         *      project_id string 必填。项目 id，由下载中心分配
         *      name string 必填。任务名称
         *      file_name string 可选。下载文件的名称，默认根据日期加随机数生成
         *      type string 可选。可选值：csv|excel。默认 csv
         *      callback string 可选。处理完成后回调通知 url
         *      step int 可选。下载数据时每次取多少数据（步长），默认 1000，可设置范围：100 - 5000
         *      operator_id string 可选。操作员编号，存根用
         *      template string 可选。表头格式定义。仅对 excel 生效
         *      title string 可选。表格标题。仅对 excel 生效
         *      summary string 可选。表格摘要。仅对 excel 生效
         *      header  array 可选。表格 header。仅对 excel 生效
         *      footer array 可选。表格 footer。仅对 excel 生效
         *      header_align string 可选。header 对齐方式
         *      footer_align string 可选。footer 对齐方式
         *      col_align string 可选。table 列对齐方式。可以被 template 中设置的 col align 覆盖
         *      default_width int 可选。表格列宽度，单位 pt。仅对 excel 生效
         *      default_height int 可选。表格行高度，单位 pt。仅对 excel 生效
         *      max_exec_time int 可选。任务处理时限（超过该时限还在“处理中”的任务将重新入列），单位秒
         *      interval int 可选。两次数据拉取之间的时间间隔，单位毫秒。取值 100 ~ 3000，默认 100
         *      rowoffset int 可选。行偏移量，第一个 table 从第几行开始渲染，默认 0，没有偏移
         */
        $this->post("/v1/task", "/V1/Task/deliver");

        /**
         * 投递任务：多表格模式。
         * 目前仅支持 excel。
         * 支持一个 tab 页生成多张表格，或者一个 excel 多个 tab，每个 tab 一张表格(暂未实现)
         * 参数和 /v1/task 单表格模式基本一致，不过相关参数是将单表格模式的参数放到数组中（source_data、template、title、summary、header、footer 以及 source_url 的响应体）
         * param:
         *      project_id string 必填。项目 id，由下载中心分配
         *      name string 必填。任务名称
         *      file_name string 可选。下载文件的名称，默认根据日期加随机数生成
         *      type string 可选。默认 excel（目前仅支持 excel）
         *      multi_type string 可选。多表格模式：page|tab。page 模式（默认）：一个页面显示多个表格；tab 模式：多个 tab，一个 tab 一张表格（暂未实现）
         *      callback string 可选。处理完成后回调通知 url
         *      operator_id string 可选。操作员编号，存根用
         *      source_data json string，源数据。多表格模式必须通过 source_data 传源数据
         *      template string 可选。表头格式定义。仅对 excel 生效
         *      title string 可选。表格标题。仅对 excel 生效
         *      summary string 可选。表格摘要。仅对 excel 生效
         *      header  array 可选。表格 header。仅对 excel 生效
         *      footer array 可选。表格 footer。仅对 excel 生效
         *      default_width int 可选。表格列宽度，单位 pt。仅对 excel 生效
         *      default_height int 可选。表格行高度，单位 pt。仅对 excel 生效
         *      max_exec_time int 可选。任务处理时限（超过该时限还在“处理中”的任务将重新入列），单位秒
         *      interval int 可选。两次数据拉取之间的时间间隔，单位毫秒。取值 100 ~ 3000，默认 100
         *      rowoffset int 可选。行偏移量，第一个 table 从第几行开始渲染，默认 0，没有偏移
         */
        $this->post("/v1/task/multiple", "/v1/Task/deliverMultiple");

        /**
         * 查询任务详情
         * params:
         *      task_id string 必填。任务编号
         */
        $this->get('/v1/task', '/V1/Task/one');

        /**
         * 根据项目查询任务列表
         * params:
         *      project_id string 必填。项目 id
         *      page int 必填。页码，从 0 开始
         *      page_size int 必填。步长，最多 200
         *      status string 可选。多个状态用英文逗号隔开，默认全部状态
         *      merchant_id int 可选。商户 id
         *      merchant_type int 可选。商户类型
         */
        $this->get('/v1/tasks', '/V1/Task/list');

        /**
         * 删除任务
         * params:
         *      task_ids string 必填。要删除的任务列表，多个用英文逗号隔开
         *      project_ids string 必填。任务所在的项目，多个用英文逗号隔开
         *      operator_id string 可选。指定删除哪个操作员的任务
         */
        $this->post('/v1/tasks/delete', 'V1/Task/delete');

        /**
         * 取数据（获取异步生成好的数据）
         * params:
         *      task_id string 必填。任务编号
         */
        $this->get('/v1/download/async', '/V1/Download/asyncGetData');

        /**
         * 获取下载临时 url
         * params:
         *      task_id string 必填。任务编号
         */
        $this->get('/v1/download/url', '/V1/Download/getDownloadUrl');

        /**
         * 同步获取数据（即不需要投递任务，而是直接调该接口生成并下载目标文件）
         * 该接口仅支持获取少量数据
         * 接口参数同 POST /v1/task 的，除了没有其中的关于异步相关的参数（如 callback）
         * 支持 GET 和 POST 两种方式
         */
        $this->get('/v1/download/sync', '/V1/Download/syncGetData');
        $this->post('/v1/download/sync', '/V1/Download/syncGetData');
        /**
         * 同步获取数据：多表格模式
         * 该接口仅支持获取少量数据
         * 接口参数同 POST /v1/task/multiple 的，除了没有其中的关于异步相关的参数（如 callback）
         * 支持 GET 和 POST 两种方式
         */
        $this->get('/v1/download/sync/multiple', '/V1/Download/syncGetDataMultiple');
        $this->post('/v1/download/sync/multiple', '/V1/Download/syncGetDataMultiple');
    }
}
