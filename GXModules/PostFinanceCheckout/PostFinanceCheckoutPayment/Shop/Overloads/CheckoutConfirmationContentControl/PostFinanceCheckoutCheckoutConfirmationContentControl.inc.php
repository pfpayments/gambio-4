<?php
    
    class PostFinanceCheckoutCheckoutConfirmationContentControl extends PostFinanceCheckoutCheckoutConfirmationContentControl_parent
    {
        public function proceed()
        {
            $currencyCheck = $_SESSION['currencyCheck'] ?? null;
            if ($_SESSION['currency'] != $currencyCheck) {
                $this->set_redirect_url(xtc_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
            }
            
            $choosenPaymentMethod = xtc_db_prepare_input($this->v_data_array['POST']['payment']) ?? '';
            if (strpos($choosenPaymentMethod, 'postfinancecheckout') === false) {
                return parent::proceed();
            }
            
            $this->v_data_array['POST']['payment'] = 'postfinancecheckout';
            $_SESSION['choosen_payment_method'] = $choosenPaymentMethod;
            parent::proceed();
        }
        
        public function get_redirect_url()
        {
            return null;
        }
    }
