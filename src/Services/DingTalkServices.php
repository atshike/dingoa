<?php

namespace Atshike\Dingoa\Services;

use AlibabaCloud\SDK\Dingtalk\Voauth2_1_0\Dingtalk as Dingtalk2;
use AlibabaCloud\SDK\Dingtalk\Voauth2_1_0\Models\GetAccessTokenRequest;
use AlibabaCloud\SDK\Dingtalk\Vworkflow_1_0\Dingtalk;
use AlibabaCloud\SDK\Dingtalk\Vworkflow_1_0\Models\GetProcessInstanceHeaders;
use AlibabaCloud\SDK\Dingtalk\Vworkflow_1_0\Models\GetProcessInstanceRequest;
use AlibabaCloud\SDK\Dingtalk\Vworkflow_1_0\Models\GetProcessInstanceResponse;
use AlibabaCloud\SDK\Dingtalk\Vworkflow_1_0\Models\ListProcessInstanceIdsHeaders;
use AlibabaCloud\SDK\Dingtalk\Vworkflow_1_0\Models\ListProcessInstanceIdsRequest;
use AlibabaCloud\SDK\Dingtalk\Vworkflow_1_0\Models\ListProcessInstanceIdsResponse;
use AlibabaCloud\SDK\Dingtalk\Vworkflow_1_0\Models\QuerySchemaByProcessCodeHeaders;
use AlibabaCloud\SDK\Dingtalk\Vworkflow_1_0\Models\QuerySchemaByProcessCodeRequest;
use AlibabaCloud\SDK\Dingtalk\Vworkflow_1_0\Models\QuerySchemaByProcessCodeResponse;
use AlibabaCloud\SDK\Dingtalk\Vworkflow_1_0\Models\StartProcessInstanceHeaders;
use AlibabaCloud\SDK\Dingtalk\Vworkflow_1_0\Models\StartProcessInstanceRequest;
use AlibabaCloud\SDK\Dingtalk\Vworkflow_1_0\Models\StartProcessInstanceRequest\formComponentValues;
use AlibabaCloud\SDK\Dingtalk\Vworkflow_1_0\Models\StartProcessInstanceResponse;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use Darabonba\OpenApi\Models\Config;
use Illuminate\Support\Facades\Cache;

class DingTalkServices
{
    private string $access_token;

    private string $cache_dingtalk_oa_access_token_key;

    private string $user_id;

    private string $appKey;

    private string $appSecret;

    public function __construct(array $config, string $user_id)
    {
        $this->cache_dingtalk_oa_access_token_key = 'dingtalk_access_token';
        $access_token = Cache::get($this->cache_dingtalk_oa_access_token_key);
        $this->appKey = $config['appKey'];
        $this->appSecret = $config['appSecret'];
        $this->user_id = $user_id;
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
        $client = new Dingtalk2($config);

        $getAccessTokenRequest = new GetAccessTokenRequest([
            'appKey' => $this->appKey,
            'appSecret' => $this->appSecret,
        ]);
        $rs = $client->getAccessToken($getAccessTokenRequest);

        $this->access_token = $rs->body->accessToken;
        Cache::put($this->cache_dingtalk_oa_access_token_key, $rs->body->accessToken, bcsub($rs->body->expireIn, 20));
    }

    /**
     * 使用 Token 初始化账号Client
     *
     * @return Dingtalk Client
     */
    public static function createClient(): Dingtalk
    {
        $config = new Config([]);
        $config->protocol = 'https';
        $config->regionId = 'central';

        return new Dingtalk($config);
    }

    /**
     * 获取审批实例ID列表.
     * https://open.dingtalk.com/document/orgapp/obtain-an-approval-list-of-instance-ids
     *
     * @param  string  $processCode 审批流的唯一码
     * @param  int  $startTime 审批实例开始时间，单位毫秒
     * @param  int|null  $endTime 审批实例结束时间
     * @param  string|null  $userIds 发起人userId
     * @param  int  $nextToken 第几页
     * @param  int  $maxResults 每页大小
     * @param  string|null  $statuses 实例状态[新创建|审批中|被终止|完成|取消]
     */
    public function processGetList(string $processCode, int $startTime, int $endTime = null, string $userIds = null, int $nextToken = 0, int $maxResults = 20, string $statuses = null): ListProcessInstanceIdsResponse
    {
        $client = self::createClient();
        $listProcessInstanceIdsHeaders = new ListProcessInstanceIdsHeaders([]);
        $listProcessInstanceIdsHeaders->xAcsDingtalkAccessToken = $this->access_token;
        $listProcessInstanceIdsRequest = new ListProcessInstanceIdsRequest([
            'processCode' => $processCode,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'nextToken' => $nextToken,
            'maxResults' => $maxResults,
            'userIds' => $userIds ?? $this->user_id,
            'statuses' => $statuses,
        ]);

        try {
            $rs = $client->listProcessInstanceIdsWithOptions($listProcessInstanceIdsRequest, $listProcessInstanceIdsHeaders, new RuntimeOptions([]));
        } catch (\Exception $err) {
            info($err->getMessage().':'.__CLASS__.':'.$processCode);
            if ($err->getCode() == 'InvalidAuthentication') {
                $this->get_access_token();
                $rs = $this->processGetList($processCode, $startTime, $endTime, $userIds, $nextToken, $maxResults, $statuses);
            }
        }

        return $rs;
    }

    /**
     * 获取审批详情.
     * https://open.dingtalk.com/document/orgapp/obtains-the-details-of-a-single-approval-instance-pop
     */
    public function processGetInfo(string $processInstanceId): GetProcessInstanceResponse
    {
        $client = self::createClient();
        $getProcessInstanceHeaders = new GetProcessInstanceHeaders([]);
        $getProcessInstanceHeaders->xAcsDingtalkAccessToken = $this->access_token;
        $getProcessInstanceRequest = new GetProcessInstanceRequest([
            'processInstanceId' => $processInstanceId,
        ]);

        return $client->getProcessInstanceWithOptions($getProcessInstanceRequest, $getProcessInstanceHeaders, new RuntimeOptions([]));
    }

    /**
     * 发起审批.
     * https://open.dingtalk.com/document/orgapp/create-an-approval-instance
     */
    public function processCreate(string $processCode, array $dataList, int $deptId = -1): ?StartProcessInstanceResponse
    {
        $client = self::createClient();
        $startProcessInstanceHeaders = new StartProcessInstanceHeaders([]);
        $startProcessInstanceHeaders->xAcsDingtalkAccessToken = $this->access_token;
        $formComponentValues = [];
        foreach ($dataList as $item) {
            $formComponentValues[] = new formComponentValues($item);
        }
        $params = [
            'originatorUserId' => $this->user_id,
            'processCode' => $processCode,
            'deptId' => $deptId,
            'formComponentValues' => $formComponentValues,
        ];
        $startProcessInstanceRequest = new StartProcessInstanceRequest($params);

        try {
            $rs = $client->startProcessInstanceWithOptions($startProcessInstanceRequest, $startProcessInstanceHeaders, new RuntimeOptions([]));
        } catch (\Exception $err) {
            info($err->getMessage().':'.__CLASS__.':'.$processCode);
        }

        return $rs ?? null;
    }

    /**
     * 获取审批流模版.
     */
    public function processCodesTemp(string $processCode): QuerySchemaByProcessCodeResponse
    {
        $client = self::createClient();
        $querySchemaByProcessCodeHeaders = new QuerySchemaByProcessCodeHeaders([]);
        $querySchemaByProcessCodeHeaders->xAcsDingtalkAccessToken = $this->access_token;
        $querySchemaByProcessCodeRequest = new QuerySchemaByProcessCodeRequest([
            'processCode' => $processCode,
        ]);

        return $client->querySchemaByProcessCodeWithOptions($querySchemaByProcessCodeRequest, $querySchemaByProcessCodeHeaders, new RuntimeOptions([]));
    }
}
