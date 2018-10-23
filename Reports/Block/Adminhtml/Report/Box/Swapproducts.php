<?php

/**
 * Class Ecommistry_Reports_Block_Adminhtml_Report_Box_Swapproducts
 */
class Ecommistry_Reports_Block_Adminhtml_Report_Box_Swapproducts extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * Ecommistry_Reports_Block_Adminhtml_Report_Box_Swapproducts constructor.
     */
    public function __construct()
    {
        $this->_blockGroup = 'ecommistry_reports';
        $this->_controller = 'adminhtml_report_box_swapproducts';
        $this->_headerText = Mage::helper('sales')->__('Swap Products');

        parent::__construct();

        $this->setTemplate('ecommistry/reports/grid/container.phtml');
        $this->_removeButton('add');
        $this->addButton(
            'filter_form_submit',
            array(
                'label'   => Mage::helper('reports')->__('Show Subscription Profiles'),
                'onclick' => 'filterFormSubmit()'
            )
        );
        $this->addButton(
            'process_form_submit',
            array(
                'label'   => Mage::helper('reports')->__('Process Swap'),
                'onclick' => 'return processSwap()'
            )
        );
    }

    /**
     * @return string
     */
    public function getFilterUrl()
    {
        $this->getRequest()->setParam('filter', null);

        return $this->getUrl('*/*/index', array('_current' => true));
    }
}
