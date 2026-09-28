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

    protected $publicKey = null;
    protected $secretKey = null;


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
