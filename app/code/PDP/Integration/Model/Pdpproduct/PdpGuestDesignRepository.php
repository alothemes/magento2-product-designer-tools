<?php
namespace PDP\Integration\Model\Pdpproduct;

use PDP\Integration\Api\PdpGuestDesignRepositoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DataObject\Factory as DataObjectFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use PDP\Integration\Api\Data\PdpDesignItemInterface;
use PDP\Integration\Helper\CorsResponseHelper;
use PDP\Integration\Plugin\CorsHeadersPlugin;

class PdpGuestDesignRepository implements PdpGuestDesignRepositoryInterface {
	
    /**
     * @var DataObjectFactory
     */
    protected $objectFactory;
	
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
	
    /**
     * @var \PDP\Integration\Model\PdpGuestDesignFactory
     */
    protected $_pdpGuestDesignFactory;
	
    /**
     * @var \PDP\Integration\Api\Data\PdpReponseInterfaceFactory
     */
    protected $pdpReponseFactory;
	
    /**
     * @var PDP\Integration\Helper\PdpOptions
     */
    protected $_pdpOptions;
	
    /**
     * PDP Integration session
     *
     * @var \PDP\Integration\Model\Session
     */
    protected $_pdpIntegrationSession;
	
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;


    /**
     * Magento HTTP Response Object
     *
     * @var  \Magento\Framework\Webapi\Rest\Response
     * @since 2.0.3
     */
    protected $_response;

    /**
     * CORS Response Helper . Add Headers to Response Object
     *
     * @var \PDP\Integration\Helper\CorsResponseHelper
     * @since 2.0.3
     */
    private $_corsResponseHelper;

    /**
     * @param DataObjectFactory                                    $objectFactory
     * @param ProductRepositoryInterface                           $productRepository
     * @param \PDP\Integration\Model\PdpGuestDesignFactory         $pdpGuestDesignFactory
     * @param \PDP\Integration\Api\Data\PdpReponseInterfaceFactory $pdpReponseFactory
     * @param \PDP\Integration\Helper\PdpOptions                   $pdpOptions
     * @param \PDP\Integration\Model\Session                       $pdpIntegrationSession
     * @param \Magento\Customer\Model\Session                      $customerSession
     * @param \Magento\Framework\Webapi\Rest\Response              $response
     * @param \PDP\Integration\Helper\CorsResponseHelper           $corsResponseHelper
     * @internal param \PDP\Integration\Plugin\CorsHeadersPlugin $corsHeadersPlugin
     */
	public function __construct(
		DataObjectFactory $objectFactory,
		ProductRepositoryInterface $productRepository,
		\PDP\Integration\Model\PdpGuestDesignFactory $pdpGuestDesignFactory,
		\PDP\Integration\Api\Data\PdpReponseInterfaceFactory $pdpReponseFactory,
		\PDP\Integration\Helper\PdpOptions $pdpOptions,
		\PDP\Integration\Model\Session $pdpIntegrationSession,
		\Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Webapi\Rest\Response $response,
        CorsResponseHelper $corsResponseHelper
	) {
		$this->objectFactory = $objectFactory;
		$this->productRepository = $productRepository;
		$this->_pdpGuestDesignFactory = $pdpGuestDesignFactory;
		$this->pdpReponseFactory = $pdpReponseFactory;
		$this->_pdpOptions = $pdpOptions;
		$this->_pdpIntegrationSession = $pdpIntegrationSession;
		$this->_customerSession = $customerSession;
		/* Inject Magento Response , Request Object */
		$this->_response = $response;
        $this->_corsResponseHelper = $corsResponseHelper;
	}
	
    /**
     * Perform persist operations for one entity
     *
     * @param PdpDesignItemInterface $pdpDesignItem
     * @return \PDP\Integration\Api\Data\PdpReponseInterface
     */
    public function save(\PDP\Integration\Api\Data\PdpDesignItemInterface $pdpDesignItem)
    {
		$reponse = $this->pdpReponseFactory->create();
		if($this->_pdpOptions->statusPdpIntegration()) {
			if($pdpDesignItem->getDesignId() && $pdpDesignItem->getProductSku()) {
				$product = $this->productRepository->get($pdpDesignItem->getProductSku());
				if($product->getTypeId()) {
					$modelGuestDesign = $this->_pdpGuestDesignFactory->create();
					$dataGuestDesign = array();
					$itemValue = array(
						'product_id' => $product->getEntityId(),
						'design_id' => $pdpDesignItem->getDesignId()
					);
					if( $pdpDesignItem->getProductId() ) {
						$itemValue['pdp_product_id'] = $pdpDesignItem->getProductId();
					} else {
						$itemValue['pdp_product_id'] = $product->getEntityId();
					}
					if($this->_customerSession->isLoggedIn()) {
						$customerId = $this->_customerSession->getCustomerId();
						$pdpGuestDesignId = $this->_pdpIntegrationSession->getPdpDesignId();
						if($pdpGuestDesignId) {
							$_dataGuestDesign = $modelGuestDesign->load($pdpGuestDesignId);
							if($_dataGuestDesign->getEntityId()) {
								try {
									if($_dataGuestDesign->getEntityId()) {
										$_dataItemVal = unserialize($_dataGuestDesign->getItemValue());
									}
									try {
										if(!$_dataGuestDesign->getCustomerId() && $_dataGuestDesign->getCustomerIsGuest()) {
											$modelGuestDesign->setId($pdpGuestDesignId)->delete();
										}
										$this->_pdpIntegrationSession->setPdpDesignId(null);
									} catch(\Magento\Framework\Exception\LocalizedException $e) {
										$reponse->setStatus(false)
												->setMessage(nl2br($e->getMessage()));
										return $reponse;
									}
									$dataGuestDesignData = $modelGuestDesign->loadByCustomerId($customerId);
									if($dataGuestDesignData->getEntityId()) {
										$dataItemVal = unserialize($dataGuestDesignData->getItemValue());
										if($dataGuestDesignData->getEntityId() != $pdpGuestDesignId && isset($_dataItemVal)) {
											foreach($_dataItemVal as $_item) {
												$dataItemVal[] = $_item;
											}
										}
										$update = false;
										foreach($dataItemVal as $__item) {
											if($__item['product_id'] == $itemValue['product_id'] && $__item['pdp_product_id'] == $itemValue['pdp_product_id'] && $__item['design_id'] == $itemValue['design_id']) {
												$update = true;
												break;
											}
										}
										if(!$update) {
											$dataItemVal[] = $itemValue;
										}
										$dataGuestDesignData->setItemValue(serialize($dataItemVal))->save();									
									} else {
										//do it later
									}
								} catch(\Magento\Framework\Exception\LocalizedException $e) {
									$reponse->setStatus(false)
											->setMessage(nl2br($e->getMessage()));
									return $reponse;
								}
							} else {
								$this->_pdpIntegrationSession->setPdpDesignId(null);
								try {
									$dataGuestDesignData = $modelGuestDesign->loadByCustomerId($customerId);
									if($dataGuestDesignData->getEntityId()) {
										$dataItemVal = unserialize($dataGuestDesignData->getItemValue());
										$update = false;
										foreach($dataItemVal as $__item) {
											if($__item['product_id'] == $itemValue['product_id'] && $__item['pdp_product_id'] == $itemValue['pdp_product_id'] && $__item['design_id'] == $itemValue['design_id']) {
												$update = true;
												break;
											}
										}
										if(!$update) {
											$dataItemVal[] = $itemValue;
										}
										
										$dataGuestDesignData->setItemValue(serialize($dataItemVal))->save();									
									} else {
										try {
											$dataGuestDesign['item_value'] = serialize([$itemValue]);
											$dataGuestDesign['customer_is_guest'] = 0;
											$dataGuestDesign['customer_id'] = $customerId;
											$modelGuestDesign->addData($dataGuestDesign)->save();
											//$pdpGuestDesignId = $modelGuestDesign->getEntityId();
											//$this->_pdpIntegrationSession->setPdpDesignId($pdpGuestDesignId);
										} catch(\Magento\Framework\Exception\LocalizedException $e) {
											$reponse->setStatus(false)
													->setMessage(nl2br($e->getMessage()));
											return $reponse;
										}
									}
								} catch(\Magento\Framework\Exception\LocalizedException $e) {
									$reponse->setStatus(false)
											->setMessage(nl2br($e->getMessage()));
									return $reponse;
								}
							}
						} else {
							try {
								$dataGuestDesignData = $modelGuestDesign->loadByCustomerId($customerId);
								if($dataGuestDesignData->getEntityId()) {
									$dataItemVal = unserialize($dataGuestDesignData->getItemValue());
									$update = false;
									foreach($dataItemVal as $__item) {
										if($__item['product_id'] == $itemValue['product_id'] && $__item['pdp_product_id'] == $itemValue['pdp_product_id'] && $__item['design_id'] == $itemValue['design_id']) {
											$update = true;
											break;
										}
									}
									if(!$update) {
										$dataItemVal[] = $itemValue;
									}
									
									$dataGuestDesignData->setItemValue(serialize($dataItemVal))->save();									
								} else {
									try {
										$dataGuestDesign['item_value'] = serialize([$itemValue]);
										$dataGuestDesign['customer_is_guest'] = 0;
										$dataGuestDesign['customer_id'] = $customerId;
										$modelGuestDesign->addData($dataGuestDesign)->save();
									} catch(\Magento\Framework\Exception\LocalizedException $e) {
										$reponse->setStatus(false)
												->setMessage(nl2br($e->getMessage()));
										return $reponse;
									}
								}
							} catch(\Magento\Framework\Exception\LocalizedException $e) {
								$reponse->setStatus(false)
										->setMessage(nl2br($e->getMessage()));
								return $reponse;
							}
						}
					} else {
						if($this->_pdpIntegrationSession->getPdpDesignId()) {
							$pdpGuestDesignId = $this->_pdpIntegrationSession->getPdpDesignId();
							try {
								$dataGuestDesignData = $modelGuestDesign->load($pdpGuestDesignId);
								if($dataGuestDesignData->getEntityId()) {
									$dataItemVal = unserialize($dataGuestDesignData->getItemValue());
									$update = false;
									foreach($dataItemVal as $__item) {
										if($__item['product_id'] == $itemValue['product_id'] && $__item['pdp_product_id'] == $itemValue['pdp_product_id'] && $__item['design_id'] == $itemValue['design_id']) {
											$update = true;
											break;
										}
									}
									if(!$update) {
										$dataItemVal[] = $itemValue;
									}								
									$dataGuestDesignData->setItemValue(serialize($dataItemVal))->save();
								} else {
									$this->_pdpIntegrationSession->setPdpDesignId(null);
									try {
										$dataGuestDesign['item_value'] = serialize([$itemValue]);
										$dataGuestDesign['customer_is_guest'] = 1;
										$modelGuestDesign->addData($dataGuestDesign)->save();
										$pdpGuestDesignId = $modelGuestDesign->getEntityId();
										$this->_pdpIntegrationSession->setPdpDesignId($pdpGuestDesignId);
									} catch(\Magento\Framework\Exception\LocalizedException $e) {
										$reponse->setStatus(false)
												->setMessage(nl2br($e->getMessage()));
										return $reponse;
									}
								}
							} catch(\Magento\Framework\Exception\LocalizedException $e) {
								$reponse->setStatus(false)
										->setMessage(nl2br($e->getMessage()));
								return $reponse;
							}
						} else {
							try {
								$dataGuestDesign['item_value'] = serialize([$itemValue]);
								$dataGuestDesign['customer_is_guest'] = 1;
								$modelGuestDesign->addData($dataGuestDesign)->save();
								$pdpGuestDesignId = $modelGuestDesign->getEntityId();
								$this->_pdpIntegrationSession->setPdpDesignId($pdpGuestDesignId);
							} catch(\Magento\Framework\Exception\LocalizedException $e) {
								$reponse->setStatus(false)
										->setMessage(nl2br($e->getMessage()));
								return $reponse;
							}
						}
					}
					$reponse->setStatus(true)
							->setMessage('add data success');
				} else {
					$reponse->setStatus(false)
							->setMessage('post data failed, product not exists');
				}
			} else {
				$reponse->setStatus(false)
						->setMessage('post data failed');				
			}
		} else {
			$reponse->setStatus(false)
					->setMessage('post data failed, PDP Integration is not enable');
		}
        $this->_corsResponseHelper->addCorsHeaders($this->_response);
        return $reponse;
	}

    /**
     * @return string
     * @since 2.0.3
     */
	public function checkCORS()
    {
        $this->_corsResponseHelper->addCorsHeaders($this->_response);
        return '';
    }
}