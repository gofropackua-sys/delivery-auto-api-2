
<?php

namespace LisDev\Delivery;

class DeliveryAutoApi2
{
    /**
     * API Delivery v4.
     */
    protected $apiUrl = 'https://www.delivery-auto.com/api/v4';

    /**
     * Выбрасывать исключения при ошибках API.
     */
    protected $throwErrors = false;

    /**
     * Формат результата: array или json.
     */
    protected $format = 'array';

    /**
     * Язык API.
     */
    protected $culture = 'uk-UA';

    /**
     * Текущая модель API.
     */
    protected $model = 'Public';

    /**
     * Текущий метод API.
     */
    protected $method = null;

    /**
     * Параметры запроса.
     */
    protected $params = [];

    /**
     * Таймаут подключения.
     */
    protected $connectTimeout = 10;

    /**
     * Таймаут запроса.
     */
    protected $timeout = 30;

    /**
     * Последняя ошибка.
     */
    protected $lastError = null;


    // ==========================================
    // КОНСТРУКТОР
    // ==========================================

    public function __construct($throwErrors = false)
    {
        $this->throwErrors = (bool) $throwErrors;
    }


    // ==========================================
    // НАСТРОЙКИ
    // ==========================================

    public function setCulture($culture)
    {
        $this->culture = $culture;

        return $this;
    }

    public function getCulture()
    {
        return $this->culture;
    }

    public function setFormat($format)
    {
        if (!in_array($format, ['array', 'json'], true)) {
            throw new \InvalidArgumentException(
                'Format must be array or json'
            );
        }

        $this->format = $format;

        return $this;
    }

    public function getFormat()
    {
        return $this->format;
    }

    public function setThrowErrors($throwErrors)
    {
        $this->throwErrors = (bool) $throwErrors;

        return $this;
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    public function setTimeout($timeout)
    {
        $this->timeout = (int) $timeout;

        return $this;
    }

    public function setConnectTimeout($timeout)
    {
        $this->connectTimeout = (int) $timeout;

        return $this;
    }


    // ==========================================
    // ОБРАБОТКА ОШИБОК
    // ==========================================

    private function error($message)
    {
        $this->lastError = $message;

        if ($this->throwErrors) {
            throw new \RuntimeException($message);
        }

        $result = [
            'status' => false,
            'message' => $message,
            'data' => null,
        ];

        if ($this->format === 'json') {
            return json_encode(
                $result,
                JSON_UNESCAPED_UNICODE
            );
        }

        return $result;
    }


    // ==========================================
    // ОБРАБОТКА ОТВЕТА
    // ==========================================

    private function prepare($response)
    {
        $result = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->error(
                'Invalid JSON: ' . json_last_error_msg()
            );
        }

        if (!is_array($result)) {
            return $this->error(
                'Invalid API response'
            );
        }

        if (
            isset($result['status']) &&
            $result['status'] === false
        ) {
            $message = $result['message'] ?? 'API error';

            if (!empty($result['errors'])) {
                $errors = $result['errors'];

                $message = is_array($errors)
                    ? implode(
                        "\n",
                        array_map(
                            function ($error) {
                                return is_scalar($error)
                                    ? (string) $error
                                    : json_encode(
                                        $error,
                                        JSON_UNESCAPED_UNICODE
                                    );
                            },
                            $errors
                        )
                    )
                    : (string) $errors;
            }

            $this->lastError = $message;

            if ($this->throwErrors) {
                throw new \RuntimeException($message);
            }
        }

        if ($this->format === 'json') {
            return $response;
        }

        return $result;
    }


    // ==========================================
    // HTTP REQUEST
    // ==========================================

    private function request(
        $model,
        $method,
        $params = null,
        $post = false
    ) {
        $this->lastError = null;

        $params = $params ?? [];

        $params['culture'] = $this->culture;

        $url = rtrim($this->apiUrl, '/')
            . '/'
            . rawurlencode($model)
            . '/'
            . rawurlencode($method);

        if (!$post) {
            $url .= '?' . http_build_query(
                $params,
                '',
                '&',
                PHP_QUERY_RFC3986
            );
        }

        $ch = curl_init();

        if ($ch === false) {
            return $this->error(
                'Cannot initialize cURL'
            );
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
        ];

        if ($post) {
            $json = json_encode(
                $params,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
            );

            $options[CURLOPT_POST] = true;

            $options[CURLOPT_POSTFIELDS] = $json;

            $options[CURLOPT_HTTPHEADER] = [
                'Accept: application/json',
                'Content-Type: application/json; charset=utf-8',
            ];
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);

        $httpCode = curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        $curlError = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            return $this->error(
                'cURL error: ' . $curlError
            );
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $decoded = json_decode($response, true);

            $message = is_array($decoded)
                ? ($decoded['message'] ?? '')
                : '';

            return $this->error(
                'HTTP ' . $httpCode .
                ($message !== '' ? ': ' . $message : '')
            );
        }

        return $this->prepare($response);
    }


    // ==========================================
    // УНИВЕРСАЛЬНЫЙ ВЫЗОВ
    // ==========================================

    public function model($model = '')
    {
        if ($model === '') {
            return $this->model;
        }

        $this->model = $model;
        $this->method = null;
        $this->params = [];

        return $this;
    }

    public function method($method = '')
    {
        if ($method === '') {
            return $this->method;
        }

        $this->method = $method;
        $this->params = [];

        return $this;
    }

    public function params($params)
    {
        $this->params = $params;

        return $this;
    }

    public function execute($post = false)
    {
        if (!$this->method) {
            return $this->error(
                'API method is not specified'
            );
        }

        return $this->request(
            $this->model,
            $this->method,
            $this->params,
            $post
        );
    }


    // ==========================================
    // 1. ОБЛАСТИ
    // ==========================================

    public function getRegionList($country = null)
    {
        return $this->request(
            'Public',
            'GetRegionList',
            [
                'country' => $country,
            ]
        );
    }


    // ==========================================
    // 2. ГОРОДА
    // ==========================================

    public function getAreasList(
        $regionId = null,
        $cityName = null,
        $flAll = false,
        $country = null
    ) {
        return $this->request(
            'Public',
            'GetAreasList',
            [
                'regionId' => $regionId,
                'cityName' => $cityName,
                'fl_all' => $flAll ? 'true' : 'false',
                'country' => $country,
            ]
        );
    }


    // ==========================================
    // 3. СПИСОК СКЛАДОВ
    // ==========================================

    public function getWarehousesList(
        $includeRegionalCenters = false,
        $cityId = null,
        $regionId = null,
        $needCenterPickUpDelivery = false,
        $country = null
    ) {
        return $this->request(
            'Public',
            'GetWarehousesList',
            [
                'includeRegionalCenters' =>
                    $includeRegionalCenters ? 'true' : 'false',

                'CityId' => $cityId,

                'RegionId' => $regionId,

                'needCenterPickUpDelivery' =>
                    $needCenterPickUpDelivery ? 'true' : 'false',

                'country' => $country,
            ]
        );
    }


    // ==========================================
    // 4. ИНФОРМАЦИЯ О СКЛАДЕ
    // ==========================================

    public function getWarehousesInfo($warehousesId)
    {
        return $this->request(
            'Public',
            'GetWarehousesInfo',
            [
                'WarehousesId' => $warehousesId,
            ]
        );
    }


    // ==========================================
    // 5. СКЛАДЫ ПО ГОРОДУ
    // ==========================================

    /**
     * DirectionType:
     *
     * 0 - склады отправления
     * 1 - склады получения
     */
    public function getWarehousesListByCity(
        $cityId,
        $directionType = 0
    ) {
        return $this->request(
            'Public',
            'GetWarehousesListByCity',
            [
                'CityId' => $cityId,
                'DirectionType' => $directionType,
            ]
        );
    }


    // ==========================================
    // 6. ПОДРОБНЫЙ СПИСОК СКЛАДОВ
    // ==========================================

    public function getWarehousesListInDetail(
        $cityId,
        $onlyWarehouses = true,
        $includeRegionalCenters = false,
        $needCenterPickUpDelivery = false,
        $country = null
    ) {
        return $this->request(
            'Public',
            'GetWarehousesListInDetail',
            [
                'CityId' => $cityId,

                'onlyWarehouses' =>
                    $onlyWarehouses ? 'true' : 'false',

                'includeRegionalCenters' =>
                    $includeRegionalCenters ? 'true' : 'false',

                'needCenterPickUpDelivery' =>
                    $needCenterPickUpDelivery ? 'true' : 'false',

                'country' => $country,
            ]
        );
    }


    // ==========================================
    // 7. ПОИСК БЛИЖАЙШИХ СКЛАДОВ
    // ==========================================

    public function getFindWarehouses(
        $count,
        $longitude,
        $latitude,
        $includeRegionalCenters = false,
        $cityId = null,
        $type = null,
        $country = null
    ) {
        return $this->request(
            'Public',
            'GetFindWarehouses',
            [
                'count' => $count,

                'Longitude' => $longitude,

                'Latitude' => $latitude,

                'includeRegionalCenters' =>
                    $includeRegionalCenters ? 'true' : 'false',

                'CityId' => $cityId,

                'Type' => $type,

                'country' => $country,
            ]
        );
    }


    // ==========================================
    // 8. ПОИСК КВИТАНЦИИ
    // ==========================================

    public function getReceiptDetails($number)
    {
        return $this->request(
            'Public',
            'GetReceiptDetails',
            [
                'number' => $number,
            ]
        );
    }


    // ==========================================
    // 9. ДАТА ПРИБЫТИЯ
    // ==========================================

    public function getDateArrival(
        $areasSendId,
        $areasResiveId,
        $dateSend,
        $warehouseSendId = null,
        $warehouseResiveId = null,
        $currency = 100000000
    ) {
        return $this->request(
            'Public',
            'GetDateArrival',
            [
                'areasSendId' => $areasSendId,

                'areasResiveId' => $areasResiveId,

                'dateSend' => $dateSend,

                'warehouseSendId' => $warehouseSendId,

                'warehouseResiveId' => $warehouseResiveId,

                'currency' => $currency,
            ]
        );
    }


    // ==========================================
    // 10. ДОПОЛНИТЕЛЬНЫЕ УСЛУГИ
    // ==========================================

    public function getDopUslugiClassification(
        $currency = 100000000,
        $citySendId = null,
        $cityReceiveId = null,
        $formalization = false
    ) {
        return $this->request(
            'Public',
            'GetDopUslugiClassification',
            [
                'currency' => $currency,

                'CitySendId' => $citySendId,

                'CityReceiveId' => $cityReceiveId,

                'formalization' =>
                    $formalization ? 'true' : 'false',
            ]
        );
    }


    // ==========================================
    // 11. ТАРИФНЫЕ КАТЕГОРИИ
    // ==========================================

    public function getTariffCategory(
        $citySendId = null,
        $cityReceiveId = null,
        $warehouseReceiveId = null
    ) {
        return $this->request(
            'Public',
            'GetTariffCategory',
            [
                'CitySendId' => $citySendId,

                'CityReceiveId' => $cityReceiveId,

                'WarehouseReceiveId' => $warehouseReceiveId,
            ]
        );
    }


    // ==========================================
    // 12. КАТЕГОРИИ ГРУЗА
    // ==========================================

    public function getCargoCategory(
        $tariffCategoryId = null
    ) {
        return $this->request(
            'Public',
            'GetCargoCategory',
            [
                'TariffCategoryId' => $tariffCategoryId,
            ]
        );
    }


    // ==========================================
    // 13. СХЕМЫ ДОСТАВКИ
    // ==========================================

    /**
     * Возможные значения:
     *
     * 0 - Склад-Склад
     * 1 - Двери-Двери
     * 2 - Склад-Двери
     * 3 - Двери-Склад
     */
    public function getDeliveryScheme(
        $citySendId = null,
        $cityReceiveId = null,
        $warehouseReceiveId = null
    ) {
        return $this->request(
            'Public',
            'GetDeliveryScheme',
            [
                'CitySendId' => $citySendId,

                'CityReceiveId' => $cityReceiveId,

                'WarehouseReceiveId' => $warehouseReceiveId,
            ]
        );
    }


    // ==========================================
    // 14. РАСЧЁТ СТОИМОСТИ
    // ==========================================

    public function postReceiptCalculate($params)
    {
        return $this->request(
            'Public',
            'PostReceiptCalculate',
            $params,
            true
        );
    }


    // ==========================================
    // 15. ПРОСТОЙ РАСЧЁТ СТОИМОСТИ
    // ==========================================

    public function postReceiptCalculateSimple(
        $areasSendId,
        $areasResiveId,
        $warehouseSendId,
        $warehouseResiveId,
        $insuranceValue,
        $dateSend,
        $countPlace,
        $weight,
        $volume,
        $deliveryScheme = 0
    ) {
        return $this->postReceiptCalculate([
            'areasSendId' => $areasSendId,

            'areasResiveId' => $areasResiveId,

            'warehouseSendId' => $warehouseSendId,

            'warehouseResiveId' => $warehouseResiveId,

            'InsuranceValue' => (float) $insuranceValue,

            'dateSend' => $dateSend,

            'deliveryScheme' => (int) $deliveryScheme,

            'category' => [
                [
                    'categoryId' =>
                        '00000000-0000-0000-0000-000000000000',

                    'countPlace' => (int) $countPlace,

                    'helf' => (float) $weight,

                    'size' => (float) $volume,
                ],
            ],
        ]);
    }

}
