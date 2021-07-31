<?php

class ControllerExtensionModuleConversion extends Controller {

    private $productsMap = [
        "external_reference" => '',
        "product_code" => [],
        "product_part_no" => [],
        "product_price" => [],
        "product_name" => [],
        "product_link" => [],
        "product_category" => [],
        "product_category_name" => [],
        "product_brand_code" => [],
        "product_brand" => [],
        "product_qty" => []
    ];

    private $orderValue = 0;
    private $profitShareEmailEndPoint = 'https://api.conectoo.com/v1/mail/send';
    private $profitShareSmsEndPoint = 'https://api.conectoo.com/v1/sms/send';


    public function index() {

        // Add JS to all pages
        $this->document->addScript('https://t.profitshare.ro/files_shared/tr/nQy.js');

        // pentru debug(local)
        if (isset($_GET['set_local_cookie'])) {
            setcookie("click_codetest", '047931867270253013f3bac476dd4809');
        }

        $clickCodeName = 'click_codetest';

        // Save order
        if (!empty($this->session->data['conversion_save_order']) && isset($_COOKIE[$clickCodeName])) {

            $this->populateProductsMap();

            $queryString = http_build_query($this->productsMap);

            $encryptedParams = $this->profitshareEncrypt($queryString, $this->config->get('module_conversion_advertiser_key'));

            $firstSideCookie = "?click_code={$_COOKIE[$clickCodeName]}";

            echo '<iframe src="//c.profitshare.ro/ca/0/'.$this->config->get('module_conversion_advertiser_code').'/p/'.$encryptedParams . $firstSideCookie .'" alt="" border="" width="1" height="1" style="border:none !important; margin:0px !important;"></iframe>';

            $this->sendProfitshareEmail($this->session->data['conversion_save_order'], $this->orderValue);
            $this->sendProfitshareSms($this->session->data['conversion_save_order']);

        }

        unset($this->session->data['conversion_save_order']);

        // Add view twig file for controller
//        return $this->load->view('extension/module/conversion');

    }

    /**
     * Event: post.order.add
     * Called after the order has been launched
     * @param $route
     * @param $data
     */
    public function eventAddOrderHistory(string $route, array $data){
        if (!empty($data[0])) {
            $this->session->data['conversion_save_order'] = (int)$data[0];
        }
    }

    private function setOrderCategories(array $product) {

        $this->load->model('catalog/category');

        $categories = $this->model_catalog_product->getCategories($product['product_id']);
        foreach ($categories as $categoryKey => $category) {

            $categoryDetails = $this->model_catalog_category->getCategory($category['category_id']);

            $this->productsMap['product_category'][] = $category['category_id'];
            $this->productsMap['product_category_name'][] = $categoryDetails['name'];

        }

    }

    private function populateProductsMap() {

        $this->load->model('checkout/order');
        $this->load->model('catalog/product');

        $orderData = $this->model_checkout_order->getOrder($this->session->data['conversion_save_order']);
        $orderProductQuery = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$orderData['order_id'] . "'");

        foreach($orderProductQuery->rows as $key => $productOrder) {

            $product = $this->model_catalog_product->getProduct($productOrder['product_id']);

            $productPrice = $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'));
            $productPrice = isset($product['special'])
                ? $this->tax->calculate($product['special'], $product['tax_class_id'], $this->config->get('config_tax'))
                : $productPrice;

            $this->orderValue += $productPrice;

            $productUrl = $this->url->link('product/product', 'product_id=' . $product['product_id']);

            $this->productsMap['product_code'][] = $product['product_id'];
            $this->productsMap['product_part_no'][] = 'partNoProdus'. $key; // WIP - nu am stiut exact ce reprezinta. fiind un test, l-am lasat asa.
            $this->productsMap['product_price'][] = $productPrice;
            $this->productsMap['product_name'][] = $product['name'];
            $this->productsMap['product_link'][] = $productUrl;
            $this->productsMap['product_brand_code'][] = $product['manufacturer_id'];
            $this->productsMap['product_brand'][] = $product['manufacturer_id'];
            $this->productsMap['product_qty'][] = $productOrder['quantity'];

            $this->setOrderCategories($product);

        }

    }

    private function profitshareEncrypt(string $plaintext, string $key) {

        $cipher = "AES-128-CBC";
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertext_raw, $key, true);
        $encode = base64_encode( $iv.$hmac.$ciphertext_raw );
        $ciphertext = bin2hex($encode);

        return $ciphertext;
    }

    private function sendProfitshareEmail(int $orderNo, int $orderValue) {

        $this->curlApiRequest($this->profitShareEmailEndPoint, [
            'email' => 'brasov@conversion.ro',
            'subject' => "Comanda cu numarul {$orderNo} a fost trimisa catre Profitshare",
            'message_content' => "Comanda {$orderNo} in valoare de {$orderValue} {$this->config->get('config_currency')} a fost trimisa catre Profitshare.",
            'sender_email' => $this->config->get('config_email'), // contact@DOMENIU_MAGAZIN
            'sender_name' => $this->config->get('config_name')
        ]);

    }

    private function sendProfitshareSms(int $orderNo)
    {
        if (empty($this->config->get('module_conversion_telephone'))) {
            return;
        }

        $this->curlApiRequest($this->profitShareSmsEndPoint, [
            'sender' => 'Verify',
            'phone' => $this->config->get('module_conversion_telephone'),
            'sms' => "Comanda cu numarul {$orderNo} a fost plasata cu succes, urmeaza sa o livram zilele urmatoare."
        ]);

    }

    private function curlApiRequest(string $endPoint, array $params) {

        // TODO -> multicurl pentru request async
        $curl = curl_init($endPoint);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->config->get('module_conversion_api_token'),
                "Accept: application/json",
                "Accept-Language: ro"
            ]
        ]);

        $response = json_decode(curl_exec($curl), true);
        $error = curl_error($curl);
        curl_close($curl);

        if (!$response['success']) {
            $this->log->write('[PROFITSHARE] - '. $response['message'] .' - ' . $endPoint);
        }

        if ($error) {
            $this->log->write('[PROFITSHARE] - Opss... can not make this request to ProfitShare - ' . $endPoint);
        }

    }

}