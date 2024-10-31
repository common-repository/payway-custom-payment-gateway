<?php
/**
 * Payway API Configuration.
 */
class PaywayAPI
{

  public $urls = [
    'tokenUrl' => 'https://paywayws.com/PaywayWS/AccessTokens',
    'saleUrl' => 'https://paywayws.com/PaywayWS/CreditCards',
    'testTokenUrl' => 'https://paywaywstest.com/PaywayWS/AccessTokens',
    'testSaleUrl' => 'https://paywaywstest.com/PaywayWS/CreditCards',
  ];

  public $token = [
    'request' => 'getPaywaySession',
    'password' => '',
    'userName' => '',
    'companyId' => '',
  ];

  public $sale = [
    'accountInputMode' => 'primaryAccountNumber',
    'cardAccount' => [
      'accountNotes1' => '',
      'accountNotes2' => '',
      'accountNotes3' => '',
      'accountNumber' => '',
      'address' => '',
      'city' => '',
      'email' => '',
      'expirationDate' => '',
      'firstName' => '',
      'fsv' => '',
      'lastName' => '',
      'middleName' => '',
      'phone' => '',
      'state' => '',
      'zip' => '',
    ],
    'cardTransaction' => [
      'amount' => '',
      'eciType' => 7,
      'sourceId' => '',
      'name' => '',
      'processorSoftDescriptor' => '',
      'tax' => 0,
      'transactionNotes1' => '',
      'transactionNotes2' => '',
      'transactionNotes3' => '',
    ],
    'paywaySessionToken' => '',
    'request' => 'sale',
  ];
}
