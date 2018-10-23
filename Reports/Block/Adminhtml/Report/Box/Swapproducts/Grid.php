<?php

/**
 * Class Ecommistry_Reports_Block_Adminhtml_Report_Box_Swapproducts_Grid
 */
class Ecommistry_Reports_Block_Adminhtml_Report_Box_Swapproducts_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Init grid parameters
     */
    public function __construct()
    {
        parent::__construct();
        $this->setCountTotals(true);
        $this->setFilterVisibility(false);
    }

    /**
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareCollection()
    {
        $filterData = $this->getFilterData();

        if ($filterData->getData('old_sku') == null) {
            return Mage_Adminhtml_Block_Widget_Grid::_prepareCollection();
        }

        $collection     = Mage::getResourceModel('sales/recurring_profile_collection');

        $customer_entity = Mage::getSingleton('core/resource')->getTableName('customer_entity');
        $collection->getSelect()->join(
            $customer_entity,
            'main_table.customer_id = ' . $customer_entity . '.entity_id', array('customer_email' => 'email')
        );

        $firstnameAttr = Mage::getModel('eav/entity_attribute')->loadByCode('customer', 'firstname');
        $lastnameAttr  = Mage::getModel('eav/entity_attribute')->loadByCode('customer', 'lastname');

        $collection->getSelect()
            ->join(array('ce1' => 'customer_entity_varchar'), 'ce1.entity_id = main_table.customer_id',
                array('firstname' => 'value'))
            ->where('ce1.attribute_id=' . $firstnameAttr->getAttributeId())// Attribute code for firstname.
            ->join(array('ce2' => 'customer_entity_varchar'), 'ce2.entity_id = main_table.customer_id',
                array('lastname' => 'value'))
            ->where('ce2.attribute_id = ' . $lastnameAttr->getAttributeId())// Attribute code for lastname.
            ->columns(new Zend_Db_Expr("CONCAT(`ce1`.`value`, ' ',`ce2`.`value`) AS customer_name"));


        $profile_item = Mage::getSingleton('core/resource')->getTableName('sales_recurring_profile_item');
        $collection->getSelect()->join(
            $profile_item,
            'main_table.profile_id = ' . $profile_item . '.profile_id', array('sku' => 'sku', 'qty' => 'qty')
        );

       $collection->getSelect()->where('sales_recurring_profile_item.sku="'.$filterData->getData('old_sku').'"');
       $collection->getSelect()->where('main_table.state = "active" OR main_table.state = "suspended" ');

        $this->setCollection($collection);

        //Process swap based on the collection
        if($filterData->getData('process')=='yes'){
            $swap = $this->processSwap($collection, $filterData->getData('old_sku'), $filterData->getData('new_sku'));
            if($swap===true){
                Mage::getSingleton('core/session')->addSuccess('Swap process completed!');
            }
            else{
                Mage::getSingleton('core/session')->addError('New sku not found. Swap process not completed.');
            }

            return Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("*/*/index", array('rand'=>time())));
        }

        return Mage_Adminhtml_Block_Widget_Grid::_prepareCollection();
    }

    /**
     * @param $collection
     * @param $old_sku
     * @param $new_sku
     * @return bool
     */
    protected function processSwap($collection, $old_sku, $new_sku)
    {
        $affectedData = array();

        $oldProductId = Mage::getModel('catalog/product')->getIdBySku($old_sku);
        $oldProduct = Mage::getModel('catalog/product')->load($oldProductId);

        $newProductId = Mage::getModel('catalog/product')->getIdBySku($new_sku);
        $newProduct = Mage::getModel('catalog/product')->load($newProductId);

        //Proceed if we can get old and new product data
        if($oldProduct->getId() && $newProduct->getId()){
            foreach ($collection as $profile) {
                $subscription = Mage::getModel('ecommistry_subscription/profile')->load($profile->getProfileId());

                //Loop through subscription items and match with old sku product
                $items = array();
                foreach ($subscription->getItemsCollection() as $item)
                {
                    $items[$item->getProductId()] = $item;
                }

                $existingItem = null;
                if($items[$newProductId]){
                    $existingItem = $items[$newProductId];
                    unset($items[$newProductId]);
                }

                foreach ($items as $prodId => $item){
                    $sku = $item->getSku();
                    $product_id = $item->getProductId();
                    if($sku==$old_sku && $oldProductId==$product_id){

                        if($existingItem) {
                            //update quantity if new product exists in subscription
                            $qty = (int)$item->getQty();
                            $item->delete();
                            $existingItemQty = (int)$existingItem->getQty();
                            $existingItem->setQty($existingItemQty + $qty);

                            //Set Additional data
                            $additionalOptions = unserialize($existingItem->getAdditionalData());
                            $additionalOptions['qty'] = $existingItemQty + $qty;
                            $additionalData = serialize($additionalOptions);
                            $existingItem->setAdditionalData($additionalData);

                            $existingItem->save();
                        } else {
                            //Swap old product with new product
                            $item->setSku($newProduct->getSku());
                            $item->setProductId($newProductId);

                            //Set Additional data
                            $additionalOptions = unserialize($item->getAdditionalData());
                            $additionalOptions['product_id'] = $newProductId;
                            $additionalOptions['sku'] = $newProduct->getSku();
                            $additionalOptions['name'] = $newProduct->getName();
                            $additionalData = serialize($additionalOptions);
                            $item->setAdditionalData($additionalData);

                            $item->save();
                        }

                        //Get customer details and notify customer by email
                        $customer = Mage::getModel('customer/customer')->load($subscription->getCustomerId());

                        $affectedData[$subscription->getProfileId()] = array('subscription_id'=>$subscription->getProfileId(), 'state'=>$subscription->getState(), 'name'=>$customer->getName(), 'email'=>$customer->getEmail());

                        $emailData = array(
                            'old_name' => $oldProduct->getName(),
                            'new_name' => $newProduct->getName(),
                            'customer' => $customer->getName()
                        );
                        $template = Mage::getStoreConfig('ecommistry_subscription/email/replace_product_template', $subscription->getStoreId());
                        if (is_numeric($template)) {
                            $template = Mage::getModel('core/email_template')->load($template);
                        } else {
                            $template = Mage::getModel('core/email_template')->loadDefault('ecommistry_box_replace_product');
                        }
                        $email = $customer->getEmail();
                        Mage::helper('ecommistry_subscription')->sendEmail($email, $customer->getName(), $template, $emailData);

                        continue;
                    }
                }

                
            }

            //Notify Moxie
            if(!empty($affectedData)){
                $subscriptionCount = 0;

                //Prepare html content of subscriptions affected and send report to admin
                $html = '<p>Product SKU <strong>"'.$old_sku.'"</strong> has been replaced with SKU <strong>"'.$new_sku.'"</strong> in the following subscriptions:</p>';
                $html .= '<table border="1" cellpadding="2" cellspacing="2"><tr><td>Profile ID</td><td>Status</td><td>Customer Email</td><td>Customer Name</td></tr>';
                foreach ($affectedData as $subs){
                    $subscriptionCount++;
                    $html .= '<tr><td>'.$subs['subscription_id'].'</td><td>'.$subs['state'].'</td><td>'.$subs['email'].'</td><td>'.$subs['name'].'</td></tr>';
                }
                $html .= '</table>';
                $html .= '<p>Total affected subscriptions: '.$subscriptionCount.'</p>';

                $template = Mage::getStoreConfig('ecommistry_subscription/email/replace_product_template_admin');
                if (is_numeric($template)) {
                    $template = Mage::getModel('core/email_template')->load($template);
                } else {
                    $template = Mage::getModel('core/email_template')->loadDefault('ecommistry_box_replace_product_admin');
                }
                $adminEmail = Mage::getStoreConfig('ecommistry_subscription/email/replace_product_email');
                $emailData['subscription_data'] = $html;
                Mage::helper('ecommistry_subscription')->sendEmail($adminEmail, 'Moxie', $template, $emailData);
            }
            return true;
        }
        else{
            return false;
        }
    }

    /**
     * @return $this
     */
    protected function _prepareColumns()
    {
        $this->addColumn('profile_id', array(
            'header'          => $this->__('Profile ID'),
            'index'           => 'profile_id',
            'html_decorators' => array('nobr'),
            'width'           => 1,
            'filter'          => false,
            'sortable'        => false,
            'filter_index' => 'main_table.profile_id'
        ));

        $this->addColumn('state', array(
            'header'          => $this->__('Status'),
            'index'           => 'state',
            'type'            => 'options',
            'filter'          => false,
            'sortable'        => false,
            'options'         => $this->getAllStates(),
            'html_decorators' => array('nobr'),
            'width'           => 1,
        ));

        $this->addColumn('customer_email', array(
            'header'       => Mage::helper('customer')->__('Customer Email'),
            'index'        => 'customer_email',
            'filter'       => false,
            'sortable'     => false,
            'filter_index' => 'customer_entity.email',
        ));

        $this->addColumn('customer_name', array(
            'header'                    => Mage::helper('customer')->__('Customer Name'),
            'align'                     => 'left',
            'index'                     => 'customer_name',
            'filter'                    => false,
            'sortable'                  => false,
            'filter_condition_callback' => array($this, '_customerNameCondition')
        ));

        $this->addExportType('*/*/exportCsv', Mage::helper('sales')->__('CSV'));
        $this->addExportType('*/*/exportExcel', Mage::helper('sales')->__('Excel'));
        $this->addExportType('*/*/exportXml', Mage::helper('sales')->__('XML'));

        return Mage_Adminhtml_Block_Widget_Grid::_prepareColumns();
    }

    /**
     * @return array
     */
    public function getAllStates()
    {
        $profile = Mage::getModel('sales/recurring_profile');
        $states  = array($profile::STATE_ACTIVE, $profile::STATE_SUSPENDED, $profile::STATE_CANCELED);

        $result = array();
        foreach ($states as $state) {
            $result[$state] = $profile->getStateLabel($state);
        }

        return $result;
    }

    /**
     * @param $collection
     * @param $column
     */
    protected function _customerNameCondition($collection, $column)
    {
        if (!$value = trim($column->getFilter()->getValue())) {
            return;
        }
        $this->getCollection()->getSelect()->where(new Zend_Db_Expr("CONCAT(`ce1`.`value`, ' ',`ce2`.`value`) LIKE '%" . trim($column->getFilter()->getValue()) . "%'"));
    }

    /**
     * @return int
     */
    public function getCountTotals()
    {
        return 0;
    }
}
