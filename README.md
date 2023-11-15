# 钉钉OA审批api


### 安装
```
composer require atshike/dingoa
```
### 配置
```
'dingtalk' => [
    'oa' => [
        'appkey_**' => env('DINGTALK_OA_APP_KEY'),
        'appsecret_**' => env('DINGTALK_OA_APP_SECRET'),
    ]
],
```
### 使用
```
use Atshike\Dingoa\Services\DingTalkServices;
use Carbon\Carbon;

$dingtalk = new DingTalkServices([
    'appKey' => '你的appKey',
    'appSecret' => '你的appSecret',
], '默认 user id');

// 发起审批
$list = [
    [
        'name' => '订单号',
        'componentType' => 'TextField',
        'id' => 'TextField-K2AD4O5B',
        'value' => 'b' . time(),
    ],
];
$rs = $dingtalk->processCreate('processCode', $list);
echo "审批实例id: {$rs->body?->instanceId}";
// 获取审批id列表
$res = $dingtalk->processGetList(
    'processCode', 
    bcmul(Carbon::now()->startOfMonth()->timestamp, 1000)，
);
$list = $res->body?->result;
foreach ($list->list as $item) {
    // 获取审批详情
    $rs = $dingtalk->processGetInfo($item);
    $info = $rs->body?->result;
    dd($info->formComponentValues);
}
```