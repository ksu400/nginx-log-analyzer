<?php

declare(strict_types=1);

namespace app\services;

class UserAgentParser
{
    private const OS_RULES = [
        'Windows NT' => 'Windows',
        'Android' => 'Android',
        'iPhone|iPad|iPod' => 'iOS',
        'Mac OS X' => 'macOS',
        'Linux' => 'Linux',
    ];

    private const ARCH_RULES = [
        'x86_64|Win64|WOW64|amd64' => 'x64',
        'i[3-6]86(?!_)' => 'x86',
    ];

    private const BROWSER_RULES = [
        'OPR\/' => 'Opera',
        'YaBrowser' => 'Yandex Browser',
        'Edg(?:e|)\/' => 'Edge',
        'Chrome\/' => 'Chrome',
        'Firefox\/' => 'Firefox',
        'MSIE|Trident' => 'Internet Explorer',
        'Safari\/' => 'Safari',
    ];

    /**
     * Разбирает строку User-Agent и возвращает определённые ОС, архитектуру и браузер.
     *
     * @param string $ua строка User-Agent из HTTP-заголовка
     */
    public function parse(string $ua): UserAgentDto
    {
        return new UserAgentDto(
            os: $this->detect($ua, self::OS_RULES, 'Other'),
            architecture: $this->detect($ua, self::ARCH_RULES, null, 'i'),
            browser: $this->detect($ua, self::BROWSER_RULES, 'Other'),
        );
    }

    /**
     * Перебирает правила и возвращает значение первого совпавшего паттерна.
     *
     * @param array<string, string> $rules паттерн => значение
     * @param string|null $default значение если ни один паттерн не совпал
     * @param string $flags флаги регулярного выражения (например 'i')
     */
    private function detect(string $ua, array $rules, ?string $default, string $flags = ''): ?string
    {
        foreach ($rules as $pattern => $value) {
            if (preg_match('/' . $pattern . '/' . $flags, $ua)) {
                return $value;
            }
        }
        return $default;
    }
}
