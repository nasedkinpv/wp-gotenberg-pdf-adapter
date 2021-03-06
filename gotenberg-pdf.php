<?php

/**
 *
 * Plugin Name: Gotenberg PDF
 * Plugin URI:  https://github.com/nasedkinpv/gotenberg-pdf
 * Description: Wordpress plugin for creating PDF files from docker container Gotenberg (thecodingmachine)
 * Version:     1.0.8
 * Author:      Ben Nasedkin
 * Author URI:  nasedk.in
 * License:     GNU GENERAL PUBLIC LICENSE
 * GitHub Plugin URI: nasedkinpv/gotenberg-pdf
 * GitHub Plugin URI: https://github.com/nasedkinpv/gotenberg-pdf
 *
 */

require "vendor/autoload.php";
include_once "options-page.php";

use TheCodingMachine\Gotenberg\Client;
use TheCodingMachine\Gotenberg\ClientException;
use TheCodingMachine\Gotenberg\RequestException;
// use TheCodingMachine\Gotenberg\Request\setAssets;

use TheCodingMachine\Gotenberg\URLRequest;

// deny direct access
if (!function_exists('add_action')) {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

// check WordPress version
global $wp_version;
if (version_compare($wp_version, '3.9', "<")) {
    exit('3.9' . ' requires WordPress ' . '3.9' . ' or newer.');
}

if (!class_exists('GOTENGERG_SERVICE')):
    class GOTENGERG_SERVICE
{
        public function __construct()
    {
            $this->print_mode = '';
            $this->generate_mode = '';
            // Plugin action links
            // add_filter('plugin_action_links', array($this, 'plugin_action_links'), 10, 2);
            // Activation hooks
            register_activation_hook(__FILE__, array($this, 'activate'));
            register_deactivation_hook(__FILE__, array($this, 'deactivate'));
            $settings = $this->get_settings();
            // Filterable init action
            add_action('init', array($this, 'init'), 0, 0);
            // $this->run(); // non-filterable init action
            add_filter('template_redirect', [$this, 'run']);
        }

        /**
         * Runs on enabling plugin
         *
         * @param null
         * @return boolean
         */
        public function activate()
    {
            // set defaults
            // $this->webserver = 'http://nginx:80';
            // $this->gotenberg_uri = 'http://gotenberg:3000';
            // $this->query_for_print = 'print';
            // $this->query_for_pdf = 'pdf';
            // $this->custom_post_type = 'page';
            // $this->pdf_folder = get_stylesheet_directory() . '/print/';
        }

        /**
         * Initialization of plugin, runs on start
         *
         * @return boolean
         */
        public function init()
    {
            // add_filter('template_redirect', [$this, 'watch_query_and_post_type']);

        }

        /**
         * Runs on disabling plugin
         *
         * @param null
         * @return boolean
         */
        public function deactivate()
    {
            // fired in actions
        }

        /**
         * Run, non-filtered
         *
         * @param null
         * @return boolean
         */
        public function template_load($altTemplate = false)
    {
            if ($altTemplate) {
                // load *-altTemplate template
                $this->template_name .= '-altTemplate';
                include_once plugin_dir_path(__FILE__) . '/themes/' . $this->template_name . '/index.php';
                exit;
            } else {
                // loads template only for print
                include_once plugin_dir_path(__FILE__) . '/themes/' . $this->template_name . '/index.php';
                exit;
            }
        }
        public function generate_handler($altTemplate = false)
    {
            $this->request_uri = $this->webserver . str_replace(get_home_url(), '', get_permalink());
            $this->file_name = self::get_pdf_filename();
            if ($altTemplate === false):
                $this->print_mode == 'regular';
                $this->request_uri .= '?' . $this->query_for_print . '=1';
                $this->generatePDF($this->request_uri, $this->file_name);
            endif;
            if ($altTemplate === true):
                $this->print_mode === 'altTemplate';
                $this->request_uri .= '?' . $this->query_for_print . '=altTemplate';
                $this->file_name = self::get_pdf_filename($altTemplate = true);
                $this->generatePDF($this->request_uri, $this->file_name, $altTemplate = true);
            endif;
        }
        public function run()
    {
            // check post type
            $bypass = true;
            if (get_post_type() == $this->custom_post_type) {

                $bypass = false;
            }

            if (is_page_template($this->custom_page_template) === true) {
                $bypass = true;

            }

            if ($bypass === true) {
                return null;

            }

            // check for  service
            if (!$this->check_service()) {
                return null;

            }

            // check non-related request
            if (isset($_REQUEST[$this->query_for_pdf])) {
                $this->generate_mode = $_REQUEST[$this->query_for_pdf];
            }

            if (isset($_REQUEST[$this->query_for_print])) {
                $this->print_mode = $_REQUEST[$this->query_for_print];
            }

            // return version for print
            if ($this->print_mode === 'altTemplate') {
                $this->template_load($altTemplate = true);
            }

            if ($this->print_mode === '1') {
                $this->template_load($altTemplate = false);
            }

            if ($this->generate_mode === 'altTemplate') {
                $this->generate_handler($altTemplate = true);
            }

            if ($this->generate_mode === '1' || $this->generate_mode === 1) {
                $this->generate_handler($altTemplate = false);
            }

            return null;
        }

        /**
         * Check availability on Gotenberg service
         *
         * @param $service_uri ? (from options page loaded in class)
         * @return boolean
         */
        public function check_service()
    {
            $host = $this->gotenberg_uri;
            $host = preg_replace('#^https?://#', '', $host);
            $connection = fsockopen($host);

            if (is_resource($connection)) {
                fclose($connection);
                return true;
            } else {

                return false;
            }
        }

        /**
         * Loads setting from admin-page
         *
         * @param null
         * @return $options
         */
        public function get_settings()
    {
            $options = [
                'webserver_uri' => '',
                'gotenberg_uri' => '',
                'query_for_print' => '',
                'query_for_pdf' => '',
                'custom_post_type' => '',
                'custom_page_template' => '',
                'template_name' => '',
            ];
            get_option('gotenberg_pdf_settings') ? $options = get_option('gotenberg_pdf_settings') : null;
            $this->webserver = 'http://' . $options['webserver_uri'] ?: 'http://nginx:80';
            $this->gotenberg_uri = 'http://' . $options['gotenberg_uri'] ?: 'http://gotenberg:3000';
            $this->query_for_print = $options['query_for_print'] ?: 'print';
            $this->query_for_pdf = $options['query_for_pdf'] ?: 'pdf';
            $this->custom_post_type = $options['custom_post_type'] ?: 'post';
            $this->custom_page_template = $options['custom_page_template'] ?: 'page';
            $this->pdf_folder = get_stylesheet_directory() . '/print/';
            $this->template_name = $options['template_name'] ?: 'default';
            return $options;
        }

        /**
         * PDF filename
         *
         * @global $post
         * @param $altTemplate
         * @return String
         */
        public function get_pdf_filename($altTemplate = false): String
    {
            global $post;
            $filename = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $filename = preg_replace('~[^\pL\d]+~u', '-', $filename);
            // $filename = iconv('UTF-8', 'ASCII//TRANSLIT', $filename);
            $filename = preg_replace('~[^-\w]+~', '', $filename);
            $filename = trim($filename, '-');
            $filename = preg_replace('~-+~', '-', $filename);
            $filename = strtolower($filename);
            $filename .= '-' . get_the_ID();
            if ($altTemplate === true) {
                $filename .= '-altTemplate';
            }

            $filename .= '.pdf';
            return $filename;
        }

        /**
         * Main function to generate print-friendly version to file
         * @var header.html
         * @var footer.html
         * @return PDF in browser and save in file
         **/

        public function generatePDF($url, $file_name, $altTemplate = false)
    {
            $client = new Client($this->gotenberg_uri, new \Http\Adapter\Guzzle6\Client());
            $file_path = $this->pdf_folder . $file_name;
            // if (file_exist()) {
            //     # code...
            // }
            // $header = DocumentFactory::makeFromPath('header.html', __DIR__ . '/themes/nordmarine/header.html');

            try {
                $request = new URLRequest($url);
                $request->setPaperSize(URLRequest::A4);
                $request->setMargins([0.4, 0.75, 0.4, 0.4]);
                $request->setWaitDelay(5.5);
                $request->setWaitTimeout(10);

                // $request->setHeader($header);
                $client->store($request, $file_path);
                // $client->post($request);
                // $fileinfo = pathinfo($file_path);
                // $sendname = $fileinfo['filename'] . '.' . strtoupper($fileinfo['extension']);

                // header('Content-type:application/pdf');
                // header('Content-Disposition:attachment; filename="' . $this->filename . '"');
                header('Content-Type: application/pdf');
                header("Content-Disposition: attachment; filename=\"$file_name\"");
                // header('Content-Length: ' . filesize($file_path));
                readfile($file_path);
            } catch (RequestException $e) {
                echo '<pre>';
                var_dump($e);
                echo '</pre>';
                # this exception is thrown if given paper size or margins are not correct.
            } catch (ClientException $e) {
                echo '<pre>';
                echo 'client error';
                var_dump($e);
                echo '</pre>';
                # this exception is thrown by the client if the API has returned a code != 200.
            }
        }
    }
endif;
$plugin = new GOTENGERG_SERVICE();
// $plugin->load();
