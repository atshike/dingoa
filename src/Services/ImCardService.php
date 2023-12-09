<?php

namespace Atshike\Dingoa\Services;

use AlibabaCloud\SDK\Dingtalk\Vim_1_0\Dingtalk as Dingtalk1;
use AlibabaCloud\SDK\Dingtalk\Vim_1_0\Models\SendInteractiveCardHeaders;
use AlibabaCloud\SDK\Dingtalk\Vim_1_0\Models\SendInteractiveCardRequest;
use AlibabaCloud\SDK\Dingtalk\Vim_1_0\Models\SendInteractiveCardResponse;
use AlibabaCloud\SDK\Dingtalk\Vim_1_0\Models\UpdateInteractiveCardHeaders;
use AlibabaCloud\SDK\Dingtalk\Vim_1_0\Models\UpdateInteractiveCardRequest;
use AlibabaCloud\SDK\Dingtalk\Vim_1_0\Models\UpdateInteractiveCardResponse;
use AlibabaCloud\SDK\Dingtalk\Vim_2_0\Dingtalk as DingTalk2;
use AlibabaCloud\SDK\Dingtalk\Vim_2_0\Models\CloseTopboxHeaders;
use AlibabaCloud\SDK\Dingtalk\Vim_2_0\Models\CloseTopboxRequest;
use AlibabaCloud\SDK\Dingtalk\Vim_2_0\Models\CloseTopboxResponse;
use AlibabaCloud\SDK\Dingtalk\Vim_2_0\Models\CreateTopboxHeaders;
use AlibabaCloud\SDK\Dingtalk\Vim_2_0\Models\CreateTopboxRequest;
use AlibabaCloud\SDK\Dingtalk\Vim_2_0\Models\CreateTopboxRequest\cardData;
use AlibabaCloud\SDK\Dingtalk\Vim_2_0\Models\CreateTopboxResponse;
use AlibabaCloud\SDK\Dingtalk\Voauth2_1_0\Dingtalk as DingtalkToken;
use AlibabaCloud\SDK\Dingtalk\Voauth2_1_0\Models\GetAccessTokenRequest;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use Darabonba\OpenApi\Models\Config;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class ImCardService
{
    private string $access_token;

    private string $cache_dingtalk_access_token_key;

    private string $appKey;

    private string $appSecret;

    public function __construct($app = null)
    {
        $this->app = $app;
        $this->appKey = config("services.dingtalk.oa.appkey_{$this->app}");
        $this->appSecret = config("services.dingtalk.oa.appsecret_{$this->app}");
        
        $this->cache_dingtalk_access_token_key = "dingtalk_access_token_{$app}";
        $access_token = Cache::get($this->cache_dingtalk_access_token_key);

        if (! empty($access_token)) {
            $this->access_token = $access_token;
        } else {
            $this->get_access_token();
        }
    }

    /**
     * 获取access_token.
     */
    private function get_access_token(): void
    {
        $config = new Config([]);
        $config->protocol = 'https';
        $config->regionId = 'central';
        $client = new DingtalkToken($config);

        $getAccessTokenRequest = new GetAccessTokenRequest([
            'appKey' => $this->appKey,
            'appSecret' => $this->appSecret,
        ]);
        $rs = $client->getAccessToken($getAccessTokenRequest);

        $this->access_token = $rs->body?->accessToken;
        Cache::put($this->cache_dingtalk_access_token_key, $rs->body->accessToken, bcsub($rs->body->expireIn, 20));
    }

    public static function createClient(): DingTalk2
    {
        $config = new Config([]);
        $config->protocol = 'https';
        $config->regionId = 'central';

        return new DingTalk2($config);
    }

    /**
     * 创建并开启互动卡片吊顶
     * https://open.dingtalk.com/document/orgapp/create-and-open-an-interactive-card-ceiling
     */
    public function topCard($cId, $coolAppCode, $outTrackId, $cardId, $cardData, $expiredTime, $callbackRouteKey = null): CreateTopboxResponse
    {
        $client = self::createClient();
        $createTopboxHeaders = new CreateTopboxHeaders([]);
        $createTopboxHeaders->xAcsDingtalkAccessToken = $this->access_token;

        $cardData = new cardData([
            'cardParamMap' => $cardData,
        ]);

        $data = [
            'outTrackId' => $outTrackId,
            'cardData' => $cardData,
            'cardTemplateId' => $cardId,
            'openConversationId' => $cId,
            'coolAppCode' => $coolAppCode,
            'conversationType' => 1,
            // 纯拉模式
            'callbackRouteKey' => $callbackRouteKey,
            'cardSettings' => [
                'pullStrategy' => (bool) $callbackRouteKey,
            ],
            'expiredTime' => $expiredTime,
        ];

        $createTopboxRequest = new CreateTopboxRequest($data);

        return $client->createTopboxWithOptions($createTopboxRequest, $createTopboxHeaders, new RuntimeOptions([]));
    }

    /**
     * 关闭吊顶卡片
     * https://open.dingtalk.com/document/orgapp/close-interactive-card-ceiling
     */
    public function closeTopCard($cId, $outTrackId, $coolAppCode): CloseTopboxResponse
    {
        $client = self::createClient();
        $closeTopboxHeaders = new CloseTopboxHeaders([]);
        $closeTopboxHeaders->xAcsDingtalkAccessToken = $this->access_token;
        $closeTopboxRequest = new CloseTopboxRequest([
            'outTrackId' => $outTrackId,
            'conversationType' => 1,
            'openConversationId' => $cId,
            'coolAppCode' => $coolAppCode,
        ]);

        return $client->closeTopboxWithOptions($closeTopboxRequest, $closeTopboxHeaders, new RuntimeOptions([]));
    }

    /**
     * 更新卡片信息
     * https://open.dingtalk.com/document/orgapp/update-dingtalk-interactive-cards-1
     */
    public function updateCard($outTrackId, $data): UpdateInteractiveCardResponse
    {
        $config = new Config([]);
        $config->protocol = 'https';
        $config->regionId = 'central';

        $client = new DingTalk1($config);
        $updateInteractiveCardHeaders = new UpdateInteractiveCardHeaders([]);
        $updateInteractiveCardHeaders->xAcsDingtalkAccessToken = $this->access_token;

        $cardData = new cardData([
            'cardParamMap' => $data,
        ]);
        $updateInteractiveCardRequest = new UpdateInteractiveCardRequest([
            'outTrackId' => $outTrackId,
            'cardData' => $cardData,
        ]);

        return $client->updateInteractiveCardWithOptions($updateInteractiveCardRequest, $updateInteractiveCardHeaders, new RuntimeOptions([]));
    }

    /**
     * 发送钉钉互动卡片
     * https://open.dingtalk.com/document/orgapp-server/send-dingtalk-interactive-cards?spm=a2q3p.21071111.0.0.365alfv4lfv4Eh
     */
    public function sendCard($cId, $outTrackId, $cardTemplateId, $data, $callbackRouteKey = null): SendInteractiveCardResponse
    {

        $config = new Config([]);
        $config->protocol = 'https';
        $config->regionId = 'central';

        $client = new DingTalk1($config);
        $sendInteractiveCardHeaders = new SendInteractiveCardHeaders([]);
        $sendInteractiveCardHeaders->xAcsDingtalkAccessToken = $this->access_token;

        $cardData = new cardData([
            'cardParamMap' => $data,
        ]);
        $sendInteractiveCardRequest = new SendInteractiveCardRequest([
            'openConversationId' => $cId,
            'outTrackId' => $outTrackId,
            'conversationType' => 1,
            'cardData' => $cardData,
            'cardTemplateId' => $cardTemplateId,
            'callbackRouteKey' => $callbackRouteKey,
            'pullStrategy' => (bool) $callbackRouteKey,
        ]);

        return $client->sendInteractiveCardWithOptions($sendInteractiveCardRequest, $sendInteractiveCardHeaders, new RuntimeOptions([]));
    }

    /**
     * 注册互动卡片回调地址
     * https://open.dingtalk.com/document/orgapp/registration-card-interaction-callback-address-1
     */
    public function registrationCardCallback($callback_url, $router_key = null)
    {
        $client = new Client();
        $url = 'https://oapi.dingtalk.com/gettoken?'.http_build_query([
            'appkey' => $this->appKey,
            'appsecret' => $this->appSecret,
        ]);
        $rs = $client->request('GET', $url);
        $res = json_decode($rs->getBody()->getContents(), true);
        $access_token = $res['access_token'] ?? null;

        $result = $client->request(
            'POST',
            "https://oapi.dingtalk.com/topapi/im/chat/scencegroup/interactivecard/callback/register?access_token={$access_token}",
            [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'form_params' => [
                    'callback_url' => $callback_url,
                    'callbackRouteKey' => $router_key,
                    'forceUpdate' => 'true',
                ],
            ],
        );

        return json_decode($result->getBody()->getContents(), true);
    }
}
