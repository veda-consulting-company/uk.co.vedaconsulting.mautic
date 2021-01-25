<?php
/**
 * Class for retrieving lists in batches.
 *
 */
class CRM_Mautic_APIBatchList {
  /**
   *
   * @var int.
   */
  protected $batchSize = 0;

  protected $offset = 0;

  protected $total = NULL;

  protected $context = '';

  protected $data = [];


  /*
   * Parameters for the list call.
   */
  protected $params = [
    'search' => '',
    'orderBy' => 'id',
    'orderByDir' => 'ASC',
    'publishedOnly' => TRUE,
    'minimal' => FALSE,
  ];

  /**
   *
   * @var Mautic\Api\Api
   */
  protected $api = NULL;

  public function getAPI() {
    return $this->api;
  }

  /**
   *
   * @param string $context
   * @param int $batchSize
   * @param [] $params
   */
  public function __construct($context, $batchSize, $params) {
    $this->batchSize = $batchSize;
    $this->api = CRM_Mautic_Connection::singleton()->newApi($context);
    $this->context = $context;
    $this->params = array_merge($this->params, $params);
  }

  public function fetchBatch() {
    $start = $this->offset;
    $limit = $this->batchSize;
    $params = $this->params;
    if (isset($this->total) && $start >= $this->total) {
      return;
    }

    $items = $this->api->getList(
        $params['search'],
        $start,
        $limit,
        $params['orderBy'],
        $params['orderByDir'],
        $params['publishedOnly'],
        true // $params['minimal']
        );
    $key = $this->api->listName();
    if (!isset($this->total)) {
      $this->total = $items['total'];
    }
    if (isset($items[$key])) {
      $this->offset += count($items[$key]);
      return $items[$key];
    }
  }

}
