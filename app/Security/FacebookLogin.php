<?php
declare(strict_types=1);

namespace App\Security;

use App\Model\UserManager;
use App\Model\Users;
use Contributte\OAuth2Client\Flow\Facebook\FacebookAuthCodeFlow;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\FacebookUser;
use Nette\Application\UI\Presenter;
use Nette\Security\AuthenticationException;
use Nette\Security\SimpleIdentity;

/**
 * Facebook login
 * @package App\Security
 * @author Vaclav Koterec
 */
class FacebookLogin
{
	const OAUTH_SERVICE = 'Facebook';

	private FacebookAuthCodeFlow $flow;

	private UserManager $userManager;

	public function __construct(FacebookAuthCodeFlow $flow, UserManager $userManager)
	{
		$this->flow = $flow;
		$this->userManager = $userManager;
	}

	/**
	 * @return void
	 * @throws \Nette\Application\AbortException
	 */
	public function authenticate(Presenter $presenter, string $linkDestinationToFacebookAuthorize)
	{
		$this->flow->getProvider()->setRedirectUri($linkDestinationToFacebookAuthorize);
		$presenter->redirectUrl($this->flow->getAuthorizationUrl());
	}

	/**
	 * @throws \Exception
	 */
	public function authorize(string $linkDestinationToFacebookAuthorize, array $parameters, bool $createAccountIfNotExist = false): SimpleIdentity
	{
		// Setup propel redirect URL
		$this->flow->getProvider()->setRedirectUri($linkDestinationToFacebookAuthorize);

		if(isset($parameters['error_code']))
		{
			throw new AuthenticationException($parameters['error_message']);
		}

		try {
			$accessToken = $this->flow->getAccessToken($parameters);
		} catch (IdentityProviderException $e) {
			// Identity provider failure, cannot get information about user
			throw new AuthenticationException($e->getMessage());
		}

		/** @var FacebookUser $oauthUser */
		$oauthUser = $this->flow->getProvider()->getResourceOwner($accessToken);

		// found existing user by OAuth
		try {
			$userIdentity = $this->userManager->authenticateByOauth(self::OAUTH_SERVICE, $oauthUser->getId(), $oauthUser->getEmail());
		} catch (AuthenticationException $e) {
			// Identity provider failure, cannot get information about user
			if($createAccountIfNotExist) {
				// Add user to system
				$this->userManager->add($oauthUser->getEmail() ? $oauthUser->getEmail() : $oauthUser->getId(),
					$oauthUser->getEmail() ?: '',
					'',
					\Nette\Utils\ArrayHash::from(array(
						"active" => false,
						"first_name" => $oauthUser->getFirstName(),
						"surname" => $oauthUser->getLastName(),
						Users::COLUMN_OAUTH_SERVICE => self::OAUTH_SERVICE,
						Users::COLUMN_OAUTH_ID => $oauthUser->getId(),
					)));
				// Authenticate again
				$userIdentity = $this->userManager->authenticateByOauth(self::OAUTH_SERVICE, $oauthUser->getId(), $oauthUser->getEmail());
			} else {
				throw new AuthenticationException($e->getMessage());
			}
		}
		return $userIdentity;
	}

}