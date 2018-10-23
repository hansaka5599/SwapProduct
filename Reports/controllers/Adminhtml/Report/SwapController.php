<?php

/**
 * Class Ecommistry_Reports_Adminhtml_Report_SwapController
 */
class Ecommistry_Reports_Adminhtml_Report_SwapController extends Mage_Adminhtml_Controller_Action
{
    /**
     * @return $this
     */
    protected function _initAction()
    {
        $this->_title($this->__('Reports'))->_title($this->__('Products'))->_title($this->__('Swap Products'));
        $this->loadLayout()
            ->_setActiveMenu('report/products')
            ->_addBreadcrumb(Mage::helper('ecommistry_reports')->__('Reports'), Mage::helper('ecommistry_reports')->__('Reports'))
            ->_addBreadcrumb(Mage::helper('ecommistry_reports')->__('Products'), Mage::helper('ecommistry_reports')->__('Products'))
            ->_addBreadcrumb(Mage::helper('ecommistry_reports')->__('Swap Products'), Mage::helper('ecommistry_reports')->__('Swap Products'));
        return $this;
    }

    /**
     * @param $blocks
     * @return $this
     */
    protected function _initReportAction($blocks)
    {
        if (!is_array($blocks)) {
            $blocks = array($blocks);
        }

        $requestData    = Mage::helper('adminhtml')->prepareFilterString($this->getRequest()->getParam('filter'));
        $params         = $this->_getDefaultFilterData();
        foreach ($requestData as $key => $value) {
            if (!empty($value)) {
                $params->setData($key, $value);
            }
        }

        foreach ($blocks as $block) {
            if ($block) {
                $block->setFilterData($params);
            }
        }
        return $this;
    }

    /**
     * Index action
     */
    public function indexAction()
    {
        $this->_initAction();

        $gridBlock       = $this->getLayout()->getBlock('adminhtml_report_box_swapproducts.grid');
        $filterFormBlock = $this->getLayout()->getBlock('grid.filter.form');
        $this->_initReportAction(array(
            $gridBlock,
            $filterFormBlock
        ));

        $this->renderLayout();
    }

    /**
     * Export reports to CSV file
     */
    public function exportCsvAction()
    {
        $fileName   = 'swap_products_'.time().'.csv';
        $grid       = $this->getLayout()->createBlock('ecommistry_reports/adminhtml_report_box_swapproducts_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse($fileName, $grid->getCsvFile());
    }

    /**
     * Export reports to Excel XML file
     */
    public function exportExcelAction()
    {
        $fileName   = 'swap_products_'.time().'.xls';
        $grid       = $this->getLayout()->createBlock('ecommistry_reports/adminhtml_report_box_swapproducts_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse($fileName, $grid->getExcelFile());
    }

    /**
     * Export reports to XML XML file
     */
    public function exportXmlAction()
    {
        $fileName   = 'swap_products_'.time().'.xml';
        $grid       = $this->getLayout()->createBlock('ecommistry_reports/adminhtml_report_box_swapproducts_grid');
        $this->_initReportAction($grid);
        $this->_prepareDownloadResponse($fileName, $grid->getExcelFile());
    }

    /**
     * @return Varien_Object
     */
    protected function _getDefaultFilterData()
    {
        return new Varien_Object(array(
            'old_sku'   => '',
            'new_sku'   => '',
            'process'   => ''
        ));
    }
}
