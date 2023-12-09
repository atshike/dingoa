<?php

use Atshike\Dingoa\Services\ImCardService;

$type = $_GET['type'];
$dingtalk = new ImCardService();
$cId = 'cid......==';
$list = [
    [
        'coolAppCode' => 'COOLAPP-1-....',
        'outTrackId' => '销售目标-test',
        'cardId' => '50689023-ec63-4c2e-9f32-a9b786ce5bc6.schema',
        'cardData' => [
            'month' => '12',
            'url' => 'dingtalk://dingtalkclient/page/link?pc_slide=true&url=https://xxxx.com/index.html',
            'rank' => 'dingtalk://dingtalkclient/page/link?pc_slide=true&url=https://xxxx.com/index.html',
            'targe' => '20100.00',
            'rate' => '28.01%',
        ],
    ],
    [
        'coolAppCode' => 'COOLAPP-1-....',
        'outTrackId' => '销售任务-test001',
        'cardId' => '9c92adda-1e1c-458b-8e56-50a97eb7270e.schema',
        'cardData' => [
            'today' => '120',
            'over' => '1',
            'rate' => '28.01%',
            'url' => 'dingtalk://dingtalkclient/page/link?pc_slide=true&url=https://xxxx.com/index.html',
        ],
        'routerKey' => 'api_ding_im_top',
    ],
];

switch ($type) {
    case 'show':
        // 显示吊顶卡片
        $expiredTime = bcmul(bcadd(time(), 20), 1000);

        foreach ($list as $item) {
            $coolAppCode = $item['coolAppCode'];
            $outTrackId = $item['outTrackId'];
            $cardId = $item['cardId'];
            $cardData = $item['cardData'];
            $routerKey = $item['routerKey'] ?? null;
            $rs = $dingtalk->topCard($cId, $coolAppCode, $outTrackId, $cardId, $cardData, $expiredTime, $routerKey);
            echo "{$item['coolAppCode']}:".$rs->body?->success ?? 'error';
        }

        break;
    case 'hidde':
        // 关闭吊顶卡片
        foreach ($list as $item) {
            $outTrackId = $item['outTrackId'];
            $coolAppCode = $item['coolAppCode'];
            $rs = $dingtalk->closeTopCard($cId, $outTrackId, $coolAppCode);
            echo "{$item['coolAppCode']}:".$rs->body?->success ?? 'error';
        }

        break;
    case 'update':
        // 更新卡片数据
        foreach ($list as $item) {
            $outTrackId = $item['outTrackId'];
            $data = $item['cardData'];
            $rs = $dingtalk->updateCard($outTrackId, $data);
            echo "{$item['outTrackId']}:".$rs->body->success ?? 'error';
        }
        break;
    case 'url':
        $rs = $dingtalk->registrationCardCallback(
            'https://xxxx.com/api/ding_im_top',
            'api_ding_im_top',
        );
        break;
    case 'send':
        // 推送消息卡片
        $rs = $dingtalk->sendCard(
            $cId,
            '实时排名--test',
            '6785a2fd-ac5d-4fb6-8b35-8e2aae130ae2.schema',
            [
                'user1' => '周润发',
                'user2' => '销售部',
                'user3' => '公司',
                'score1' => '100',
                'score2' => '80',
                'score3' => '50',
            ],
            'api_ding_im_top',
        );

        echo $rs->body?->result?->processQueryKey ?? 'error';
        break;
    default:
        echo 'error';
        break;
}
