<?php

declare(strict_types=1);

/*
 * This file is part of Exchanger.
 *
 * (c) Florian Voutzinos <florian@voutzinos.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Exchanger\Service;

use Exchanger\Contract\ExchangeRateQuery;
use Exchanger\Contract\HistoricalExchangeRateQuery;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Exception\UnsupportedDateException;
use Exchanger\ExchangeRate;
use Exchanger\StringUtil;
use Exchanger\Contract\ExchangeRate as ExchangeRateContract;

/**
 * Russian Central Bank Service.
 */
final class RussianCentralBank extends HistoricalService
{
    const URL = 'http://www.cbr.ru/scripts/XML_daily.asp';

    /**
     * {@inheritdoc}
     */
    protected function getLatestExchangeRate(ExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $baseCurrency = $exchangeQuery->getCurrencyPair()->getBaseCurrency();

        $content = $this->request(self::URL);
        $element = StringUtil::xmlToElement($content);

        $elements = $element->xpath('./Valute[CharCode="'.$baseCurrency.'"]');
        $date = \DateTime::createFromFormat('!d.m.Y', (string) $element['Date']);

        if (empty($elements) || !$date) {
            throw new UnsupportedCurrencyPairException($exchangeQuery->getCurrencyPair(), $this);
        }

        $rate = str_replace(',', '.', (string) $elements['0']->Value);
        $nominal = str_replace(',', '.', (string) $elements['0']->Nominal);

        return new ExchangeRate((float) $rate / $nominal, __CLASS__, $date);
    }

    /**
     * {@inheritdoc}
     */
    protected function getHistoricalExchangeRate(HistoricalExchangeRateQuery $exchangeQuery): ExchangeRateContract
    {
        $baseCurrency = $exchangeQuery->getCurrencyPair()->getBaseCurrency();
        $formattedDate = $exchangeQuery->getDate()->format('d.m.Y');

        $content = $this->request(self::URL.'?'.http_build_query(['date_req' => $formattedDate]));
        $element = StringUtil::xmlToElement($content);

        $elements = $element->xpath('./Valute[CharCode="'.$baseCurrency.'"]');

        if (empty($elements)) {
            if ((string) $element['Date'] !== $exchangeQuery->getDate()->format('d.m.Y')) {
                throw new UnsupportedDateException($exchangeQuery->getDate(), $this);
            }

            throw new UnsupportedCurrencyPairException($exchangeQuery->getCurrencyPair(), $this);
        }

        $rate = str_replace(',', '.', (string) $elements['0']->Value);
        $nominal = str_replace(',', '.', (string) $elements['0']->Nominal);

        return new ExchangeRate((float) ($rate / $nominal), __CLASS__, $exchangeQuery->getDate());
    }

    /**
     * {@inheritdoc}
     */
    public function supportQuery(ExchangeRateQuery $exchangeQuery): bool
    {
        return 'RUB' === $exchangeQuery->getCurrencyPair()->getQuoteCurrency();
    }
}
