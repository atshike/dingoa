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
// 发起审批
use Atshike\Dingoa\Services\DingTalkServices;
use Carbon\Carbon;

$dingtalk = new DingTalkServices();
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

```
$dingtalk = new DingTalkServices('hy');
$data = getData($dingtalk, 'PROC--***', 1, $startTime, $endTime);
dd($data);
function getData($dingtalk, $processCode, $next, $startTime, $endTime, $user_id = null, $data = [])
{
    $rs = $dingtalk->processGetList($processCode, $startTime, $endTime, $user_id, $next);
    $list = $rs->body?->result?->list;
    $data = array_merge($data, $list);
    $nextToken = $rs->body?->result?->nextToken;
    if (!empty($nextToken)) {
        echo "下一页:{$nextToken} \n";
        $data = getData($dingtalk, $processCode, $nextToken, $startTime, $endTime, $user_id, $data);
    }

    return $data;
}

echo "获取详情：\n";
foreach ($data as $key => $item) {
    $rs = $dingtalk->processGetInfo($item);
    echo "标题：{$rs->body?->result?->title} \n";
}
        
```