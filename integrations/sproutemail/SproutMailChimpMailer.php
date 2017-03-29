<?php
namespace Craft;

/**
 * Enables you to send your campaigns using MailChimp
 *
 * Class SproutMailchimpMailer
 *
 * @package Craft
 */
class SproutMailChimpMailer extends SproutEmailBaseMailer implements SproutEmailCampaignEmailSenderInterface
{
	public $client;

	public function init()
	{
		$this->settings = craft()->plugins->getPlugin('sproutMailChimp')->getSettings();

		$client = new \Mailchimp($this->settings->getAttribute('apiKey'));

		$this->client = $client;
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return 'mailchimp';
	}

	/**
	 * @return string
	 */
	public function getTitle()
	{
		return 'MailChimp';
	}

	/**
	 * @return string
	 */
	public function getDescription()
	{
		return Craft::t('Send your email campaigns via MailChimp.');
	}

	/**
	 * @return string
	 */
	public function getCpSettingsUrl()
	{
		return UrlHelper::getCpUrl('settings/plugins/sproutmailchimp');
	}

	/**
	 * @return array
	 */
	public function defineSettings()
	{
		return array(
			'inlineCss' => array(AttributeType::Bool, 'default' => false),
			'apiKey'    => array(AttributeType::String, 'required' => true)
		);
	}

	/**
	 * @return BaseModel
	 */
	public function getSettings()
	{
		$plugin = craft()->plugins->getPlugin('sproutMailChimp');

		return $plugin->getSettings();
	}

	/**
	 * @return array
	 */
	public function getRecipientLists()
	{
		$settings = isset($settings['settings']) ? $settings['settings'] : $this->getSettings();

		$html = craft()->templates->render('sproutmailchimp/_settings/plugin', array(
			'settings' => $settings
		));

		return TemplateHelper::getRaw($html);
	}

	/**
	 * @param SproutEmail_CampaignEmailModel $campaignEmail
	 * @param SproutEmail_CampaignTypeModel  $campaignType
	 *
	 * @return array|void
	 */
	public function sendCampaignEmail(SproutEmail_CampaignEmailModel $campaignEmail, SproutEmail_CampaignTypeModel $campaignType)
	{
		$response = new SproutEmail_ResponseModel();

		try
		{
			$params = array(
				'email'     => $campaignEmail,
				'campaign'  => $campaignType,
				'recipient' => array(
					'firstName' => 'First',
					'lastName'  => 'Last',
					'email'     => 'user@domain.com'
				),

				// @deprecate - in favor of `email` in v3
				'entry'     => $campaignEmail
			);

			$html = sproutEmail()->renderSiteTemplateIfExists($campaignType->template, $params);
			$text = sproutEmail()->renderSiteTemplateIfExists($campaignType->template . '.txt', $params);

			// @todo - update to use new listSettings
			$lists = array();

			$mailChimpModel             = new SproutMailChimp_CampaignModel();
			$mailChimpModel->title      = $campaignEmail->title;
			$mailChimpModel->subject    = $campaignEmail->title;
			$mailChimpModel->from_name  = $campaignEmail->fromName;
			$mailChimpModel->from_email = $campaignEmail->fromEmail;
			$mailChimpModel->lists      = $lists;
			$mailChimpModel->html       = $html;
			$mailChimpModel->text       = $text;

			$sentCampaign = sproutMailChimp()->sendCampaignEmail($mailChimpModel);

			$sentCampaignIds = $sentCampaign['ids'];

			$response->emailModel = $sentCampaign['emailModel'];

			$response->success = true;
			$response->message = Craft::t('Campaign successfully sent to {count} recipient lists.', array(
				'count' => count($sentCampaignIds)
			));
		}
		catch (\Exception $e)
		{
			$response->success = false;
			$response->message = $e->getMessage();

			sproutEmail()->error($e->getMessage());
		}

		$response->content = craft()->templates->render('sproutmailchimp/_modals/sendEmailConfirmation', array(
			'mailer'  => $campaignEmail,
			'success' => $response->success,
			'message' => $response->message
		));

		return $response;
	}

	public function includeModalResources()
	{
		craft()->templates->includeJsResource('sproutmailchimp/js/mailchimp.js');
	}

	/**
	 * @param SproutEmail_CampaignEmailModel $campaignEmail
	 * @param SproutEmail_CampaignTypeModel  $campaignType
	 *
	 * @return string
	 */
	public function getPrepareModalHtml(SproutEmail_CampaignEmailModel $campaignEmail, SproutEmail_CampaignTypeModel $campaignType)
	{
		if (strpos($campaignEmail->replyToEmail, '{') !== false)
		{
			$campaignEmail->replyToEmail = $campaignEmail->fromEmail;
		}

		$listSettings = $campaignEmail->listSettings;

		$lists = array();

		if (!isset($listSettings['listIds']))
		{
			throw new Exception(Craft::t('No list settings found. <a href="{cpEditUrl}">Add a list</a>', array(
				'cpEditUrl' => $campaignEmail->getCpEditUrl()
			)));
		}

		if (is_array($listSettings['listIds']) && count($listSettings['listIds']))
		{
			foreach ($listSettings['listIds'] as $list)
			{
				$currentList = $this->getListById($list);

				array_push($lists, $currentList);
			}
		}

		return craft()->templates->render('sproutmailchimp/_modals/sendEmailPrepare', array(
			'mailer'        => $this,
			'campaignEmail' => $campaignEmail,
			'campaignType'  => $campaignType,
			'lists'         => $lists,
		));
	}

	/**
	 * @param $id
	 *
	 * @throws \Exception
	 * @return array|null
	 */
	public function getListById($id)
	{
		$params = array('list_id' => $id);

		try
		{
			$lists = $this->client->lists->getList($params);

			if (isset($lists['data']) && ($list = array_shift($lists['data'])))
			{
				return $list;
			}
		}
		catch (\Exception $e)
		{
			throw $e;
		}
	}

	/**
	 * @return bool|string
	 */
	public function getLists()
	{
		try
		{
			$lists = $this->client->lists->getList();

			if (isset($lists['data']))
			{
				return $lists['data'];
			}
		}
		catch (\Exception $e)
		{
			if ($e->getMessage() == 'API call to lists/list failed: SSL certificate problem: unable to get local issuer certificate')
			{
				return false;
			}
			else
			{
				return $e->getMessage();
			}
		}
	}

	/**
	 * Renders the recipient list UI for this mailer
	 *
	 * @param SproutEmail_CampaignEmailModel[]|null $values
	 *
	 * @return string Rendered HTML content
	 */
	public function getListsHtml(array $values = null)
	{
		$lists = $this->getLists();

		$options  = array();
		$selected = array();
		$errors   = array();

		if (count($lists))
		{
			foreach ($lists as $list)
			{
				if (isset($list['id']) && isset($list['name']))
				{
					$length = 0;

					if ($lists = sproutMailChimp()->getListStatsById($list['id']))
					{
						$length = number_format($lists['member_count']);
					}

					$listUrl = "https://us7.admin.mailchimp.com/lists/members/?id=" . $list['web_id'];

					$options[] = array(
						'label' => sprintf('<a target="_blank" href="%s">%s (%s)</a>', $listUrl, $list['name'], $length),
						'value' => $list['id']
					);
				}
			}
		}
		else
		{
			if ($lists === false)
			{
				$errors[] = Craft::t('Unable to retrieve lists due to an SSL certificate problem: unable to get local issuer certificate. Please contact you server administrator or hosting support.');
			}
			else
			{
				$errors[] = Craft::t('No lists found. Create your first list in MailChimp.');
			}
		}

		if (is_array($values) && count($values))
		{
			foreach ($values as $value)
			{
				$selected[] = $value->list;
			}
		}

		return craft()->templates->render('sproutmailchimp/_settings/lists', array(
			'options' => $options,
			'values'  => $selected,
			'errors'  => $errors
		));
	}

	/**
	 * @param SproutEmail_CampaignEmailModel $campaignEmail
	 * @param SproutEmail_CampaignTypeModel  $campaign
	 *
	 * @return array|SproutEmail_CampaignEmailModel
	 */
	public function prepareLists(SproutEmail_CampaignEmailModel $campaignEmail)
	{
		// @todo - update to use new $listSettings
		$lists = array();

		return $lists;
	}
}
