<?php
namespace Craft;

class SproutMailChimpController extends BaseController
{
	public function actionSaveSettings()
	{
		$mailchimp = craft()->request->getPost('mailchimp');

		$mailchimpPlugin = craft()->plugins->getPlugin('sproutMailChimp');

		$settings = $mailchimpPlugin->getSettings();

		if (empty($mailchimp['apiKey']))
		{
			$settings->addError('apiKey', Craft::t("API key cannot be blank."));
		}
		else
		{
			$result = sproutMailChimp()->getValidApi($mailchimp['apiKey']);

			if (!$result)
			{
				$settings->addError('apiKey', Craft::t("API key is invalid."));
			}
		}

		if (!$settings->hasErrors())
		{
			$settings = craft()->plugins->savePluginSettings( $mailchimpPlugin, $mailchimp );
		}
		else
		{
			craft()->userSession->setError(Craft::t('Unable to save API settings.'));

			craft()->urlManager->setRouteVariables(array(
				'settings' => $settings
			));
		}
	}

	public function actionEditSettings()
	{
		$settings = sproutMailChimp()->getSettings();

		$this->renderTemplate('sproutmailchimp/settings', array(
			'settings' => $settings
		));
	}
}
