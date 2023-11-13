<?php

use Atshike\Dingoa\Services\DingTalkServices;
use Carbon\Carbon;

$dingtalk = new DingTalkServices([
    'appKey' => 'appKey',
    'appSecret' => 'appSecret',
], 'user id');
// 发起审批
$list = [
    [
        'name' => '订单号',
        'componentType' => 'TextField',
        'id' => 'TextField-K2AD4O5B',
        'value' => 'b'.time(),
    ],
    [
        'componentType' => 'TableField',
        'id' => 'TableField_J6JWVEQ518W0',
        'name' => '表格',
        'value' => json_encode([
            [
                [
                    'name' => '物资名称',
                    'value' => '测试1',
                ],
                [
                    'name' => '规格',
                    'value' => 'a1',
                ],
                [
                    'name' => '金额（元）',
                    'value' => '11',
                ],
            ],
            [
                [
                    'name' => '物资名称',
                    'value' => '测试2',
                ],
                [
                    'name' => '规格',
                    'value' => 'a2',
                ],
                [
                    'name' => '金额（元）',
                    'value' => '10',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE),
    ],
];
$rs = $dingtalk->processCreate('processCode', $list);
echo "审批实例id: {$rs->body?->instanceId}";
// 获取审批id列表
$res = $dingtalk->processGetList('processCode', bcmul(Carbon::now()->startOfMonth()->timestamp, 1000));
$list = $res->body->result;
foreach ($list->list as $item) {
    // 获取审批详情
    $rs = $dingtalk->processGetInfo($item);
    $info = $rs->body->result;
    dd($info->formComponentValues);
}
