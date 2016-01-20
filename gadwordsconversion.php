<?php
/*
* The MIT License (MIT)
*
* Copyright (c) 2016 Benichou
*
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included in all
* copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
* SOFTWARE.
*
*  @author    Benichou <benichou.software@gmail.com>
*  @copyright 2016 Benichou
*  @license   http://opensource.org/licenses/MIT  The MIT License (MIT)
*/

if (!defined('_PS_VERSION_'))
	exit;

class GadwordsConversion extends Module
{
	public function __construct()
	{
		$this->name = 'gadwordsconversion';
		$this->tab = 'analytics_stats';
		$this->author = 'Benichou';
		$this->version = '1.0';
		$this->bootstrap = true;

		parent::__construct();
		$this->displayName = $this->l('Google AdWords Conversion Tracking');
		$this->description = $this->l('Adding Google AdWords Conversion Tracking script');
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
	}

	public function install()
	{
		if (!parent::install() || !$this->registerHook('orderConfirmation') || !$this->registerHook('displayCustomerAccount'))
			return false;

		return true;
	}

	public function uninstall()
	{
		if(!$this->unregisterHook('orderConfirmation') || !$this->unregisterHook('displayCustomerAccount'))
			return false;

		Configuration::deleteByName('ADWORDS_CONVERSION_ID');
		Configuration::deleteByName('ADWORDS_ORDER_CONVERSION_LABEL');
		Configuration::deleteByName('ADWORDS_SIGNUP_CONVERSION_LABEL');

		return parent::uninstall();
	}

	public function getContent()
	{
		$output = '';

		// If form has been sent
		if (Tools::isSubmit('submit'.$this->name))
		{
			Configuration::updateValue('ADWORDS_CONVERSION_ID', Tools::getValue('ADWORDS_CONVERSION_ID'));
			Configuration::updateValue('ADWORDS_ORDER_CONVERSION_LABEL', Tools::getValue('ADWORDS_ORDER_CONVERSION_LABEL'));
			Configuration::updateValue('ADWORDS_SIGNUP_CONVERSION_LABEL', Tools::getValue('ADWORDS_SIGNUP_CONVERSION_LABEL'));

			$output .= $this->displayConfirmation($this->l('Settings updated successfully'));
		}

		$output .= $this->renderForm();
		return $output;
	}

	public function renderForm()
	{
		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;

		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');

		// Title and toolbar
		$helper->title = $this->displayName;
		$helper->submit_action = 'submit'.$this->name;

		$fields_forms = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('General settings'),
					'icon' => 'icon-cogs'
				),
				'input' => array(
					array(
						'type' => 'text',
						'label' => $this->l('Conversion ID'),
						'name' => 'ADWORDS_CONVERSION_ID',
						'size' => 40,
						'required' => true,
						'hint' => $this->l('Find "var google_conversion_id = " and enter it here.')
					),
					array(
						'type' => 'text',
						'label' => $this->l('Order conversion label'),
						'name' => 'ADWORDS_ORDER_CONVERSION_LABEL',
						'size' => 40,
						'required' => true,
						'hint' => $this->l('Google conversion label for tracking orders.')
					),
					array(
						'type' => 'text',
						'label' => $this->l('Signup conversion label'),
						'name' => 'ADWORDS_SIGNUP_CONVERSION_LABEL',
						'size' => 40,
						'required' => false,
						'hint' => $this->l('Google conversion label for tracking new account creation.')
					)
				),
				'submit' => array(
					'title' => $this->l('Save')
				)
			)
		);

		// Load current value
		$helper->fields_value['ADWORDS_CONVERSION_ID'] = Configuration::get('ADWORDS_CONVERSION_ID');
		$helper->fields_value['ADWORDS_ORDER_CONVERSION_LABEL'] = Configuration::get('ADWORDS_ORDER_CONVERSION_LABEL');
		$helper->fields_value['ADWORDS_SIGNUP_CONVERSION_LABEL'] = Configuration::get('ADWORDS_SIGNUP_CONVERSION_LABEL');

		return $helper->generateForm(array($fields_forms));
	}

	private function hookConversion($params, $conversion_label)
	{
		$conversion_id = intval(Configuration::get('ADWORDS_CONVERSION_ID'));
		if (!$conversion_id)
			return;

		Media::addJsDef(array(
			'google_conversion_id' => $conversion_id,
			'google_conversion_language' => "en",
			'google_conversion_format' => "3",
			'google_conversion_color' => "ffffff",
			'google_conversion_label' => $conversion_label,
			'google_remarketing_only' => false,
		));

		$protocol_link = (Configuration::get('PS_SSL_ENABLED') || Tools::usingSecureMode()) ? 'https://' : 'http://';
		$this->context->controller->addJs($protocol_link.'www.googleadservices.com/pagead/conversion.js');

		$this->context->smarty->assign(array(
			'gadwords_conversion_id' => $conversion_id,
			'gadwords_conversion_label' => $conversion_label,
			'gadwords_remarketing_only' => false,
		));

		return $this->display(__FILE__, 'noscript.tpl');
	}

	public function hookOrderConfirmation($params)
	{
		$conversion_label = Configuration::get('ADWORDS_ORDER_CONVERSION_LABEL');
		if (empty($conversion_label))
			return;

		$order = new Order((int)Tools::getValue('id_order'));
		$currency = new Currency($order->id_currency);
		$value = number_format($order->getOrdersTotalPaid(), 2);

		Media::addJsDef(array(
			'google_conversion_value' => $value,
			'google_conversion_currency' => $currency->iso_code
		));

		$this->context->smarty->assign(array(
			'gadwords_conversion_value' => $value,
			'gadwords_conversion_currency' => $currency->iso_code
		));

		return $this->hookConversion($params, $conversion_label);
	}

	public function hookDisplayCustomerAccount($params)
	{
		$conversion_label = Configuration::get('ADWORDS_SIGNUP_CONVERSION_LABEL');
		if (empty($conversion_label) || empty($this->context->smarty->getTemplateVars('account_created')))
			return;

		return $this->hookConversion($params, $conversion_label);
	}
}
?>
