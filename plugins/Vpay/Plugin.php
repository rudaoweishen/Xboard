<?php

namespace Plugin\Vpay;

use App\Services\Plugin\AbstractPlugin;
use App\Contracts\PaymentInterface;

class Plugin extends AbstractPlugin implements PaymentInterface
{
    public function boot(): void
    {
        $this->filter('available_payment_methods', function ($methods) {
            if ($this->getConfig('enabled', true)) {
                $methods['VPay'] = [
                    'name' => $this->getConfig('display_name', 'V支付'),
                    'icon' => $this->getConfig('icon', '💳'),
                    'plugin_code' => $this->getPluginCode(),
                    'type' => 'plugin'
                ];
            }
            return $methods;
        });
    }

    public function form(): array
    {
        return [
            'url' => [
                'label' => '支付网关地址',
                'type' => 'string',
                'required' => true,
                'description' => '请填写完整的支付网关地址，包括协议（http或https）'
            ],
            'pid' => [
                'label' => '商户ID',
                'type' => 'string',
                'description' => '请填写商户ID',
                'required' => true
            ],
            'key' => [
                'label' => '通信密钥',
                'type' => 'string',
                'required' => true,
                'description' => '请填写通信密钥'
            ],
            'type' => [
                'label' => '支付类型',
                'type' => 'select',
                'options' => [
                    ['value' => '2', 'label' => '支付宝'],
                    ['value' => '1', 'label' => '微信支付'],
                    ['value' => '4', 'label' => 'QQ钱包']
                ]
            ],
        ];
    }


public function pay($order): array
{
    $params = [
        'price' => $order['total_amount'] / 100,
        	'payId' => $order['trade_no'],
        	'out_trade_no' => $order['trade_no'],
        'notifyUrl' => $order['notify_url'],
        'returnUrl' => $order['return_url'],
        'param' => $order['trade_no'],
        'pid' => $this->getConfig('pid')
    ];
    error_log('Response Data: ' . print_r($params, true));
    if ($paymentType = $this->getConfig('type')) {
        $params['type'] = $paymentType;
    }
    ksort($params);
    $signStr = 'payId=' . $params['payId'] . '&param=' . $params['param'] . '&type=' . $params['type'] . '&price=' . $params['price'] . '&key=' . $this->getConfig('key');
    $params['sign'] = md5($signStr);
    $apiurl = $this->getConfig('url') . '/api/order/create';
    $response = file_get_contents($apiurl . '?' . http_build_query($params));
    $responseData = json_decode($response, true);

    if ($responseData['code'] === 200) {
        if (isset($responseData['data']) && is_array($responseData['data'])) {
            $redirectUrl = $responseData['data']['redirectUrl'] ?? null;
            error_log('Original Redirect URL: ' . $redirectUrl);

            if ($redirectUrl && strpos($redirectUrl, 'http://') === 0) {
                $redirectUrl = 'https://' . substr($redirectUrl, 7);
            }
            error_log('Updated Redirect URL: ' . $redirectUrl);

            if ($redirectUrl) {
                return [
                    'type' => 1,
                    'data' => $redirectUrl
                ];
            }
        }
    }

    return [
        'type' => 'error',
        'message' => '支付请求失败，未找到跳转链接'
    ];
}

    public function notify($params): array|bool
    {
	$key = "secret_key";
	$payId = $params['payId'];
	$param = $params['param'];
	$type = $params['type'];
	$price = $params['price'];
	$reallyPrice = $params['reallyPrice'];
	$sign = $params['sign'];
	
	$_sign =  md5($payId . $param . $type . $price . $reallyPrice . $key);
	if ($_sign != $sign) {
	return false;
	}

	return [
        'trade_no' => $payId,
	    'callback_no' => $payId,
        ];
    }
}
