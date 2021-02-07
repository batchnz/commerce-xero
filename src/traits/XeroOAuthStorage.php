<?php

namespace thejoshsmith\xero\traits;

use Craft;

use League\OAuth2\Client\Token\AccessTokenInterface;
use Calcinai\OAuth2\Client\XeroResourceOwner;
use Calcinai\OAuth2\Client\XeroTenant;
use thejoshsmith\xero\records\Connection;
use thejoshsmith\xero\records\Credential;
use thejoshsmith\xero\records\ResourceOwner;
use thejoshsmith\xero\records\Tenant;

/**
 * Xero API Storage Trait
 *
 * Provides methods to interface with storing Xero OAuth API credentials
 *
 * PHP version 7.4
 *
 * @category  Traits
 * @package   CraftCommerceXero
 * @author    Josh Smith <hey@joshthe.dev>
 * @copyright 2021 Josh Smith
 * @license   Proprietary https://github.com/thejoshsmith/craft-commerce-xero/blob/master/LICENSE.md
 * @version   GIT: $Id$
 * @link      https://joshthe.dev
 * @since     1.0.0
 */

 /**
  * Xero API Storage Trait
  *
  * @category Traits
  * @package  CraftCommerceXero
  * @author   Josh Smith <by@joshthe.dev>
  * @license  Proprietary https://github.com/thejoshsmith/craft-commerce-xero/blob/master/LICENSE.md
  * @link     https://joshthe.dev
  * @since    1.0.0
  */
trait XeroOAuthStorage
{
    /**
     * Persists a Xero connection including the identity,
     * access credentials and a list of connected tenants
     *
     * @param XeroResourceOwner    $identity    Resource Owner from Xero
     * @param AccessTokenInterface $accessToken Access Token from Xero
     * @param array                $xeroTenants An array of connected tenants
     * @param integer              $userId      Craft user this connection belongs to, defaults to logged in user ID
     * @param integer              $siteId      Craft site this connection belongs to, defaults to current site
     *
     * @return void
     */
    public function saveXeroConnection(
        XeroResourceOwner $identity,
        AccessTokenInterface $accessToken,
        array $xeroTenants,
        int $userId = null,
        int $siteId = null
    ) {
        $connections = [];

        if (empty($xeroTenants)) {
            return $connections;
        }

        // Get the current logged in user
        if (empty($userId)) {
            $userId = Craft::$app->getUser()->getId();
        }

        // Get the current site ID
        if (empty($siteId)) {
            $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {

            $resourceOwner = $this->saveResourceOwner($identity);

            $credential = $this->saveCredential(
                Credential::populateFromAccessToken($accessToken)
            );

            // Now, save each tenant connection
            foreach ($xeroTenants as $xeroTenant) {
                $tenant = $this->saveTenant($xeroTenant);
                $connections[] = $this->saveConnection(
                    $xeroTenant->id, $resourceOwner, $credential, $tenant, $userId, $siteId
                );
            }

            $transaction->commit();
        } catch(\Exception $e) {
            $transaction->rollBack();
        }

        return $connections;
    }

    public function saveConnection(
        string $connectionId,
        ResourceOwner $resourceOwner,
        Credential $credential,
        Tenant $tenant,
        int $userId,
        int $siteId
    ): Connection {
        $connection = new Connection(
            [
            'connectionId' => $connectionId,
            'credentialId' => $credential->id,
            'resourceOwnerId' => $resourceOwner->id,
            'tenantId' => $tenant->id,
            'userId' => $userId,
            'siteId' => $siteId,
            ]
        );

        $existingConnection = Connection::find()->where(
            [
            'connectionId' => $connectionId
            ]
        )->one();

        if (!empty($existingConnection)) {
            $connection->id = $existingConnection->id;
            $connection->setIsNewRecord(false);
        }

        $connection->save();

        return $connection;
    }

    /**
     * Saves a Resource Owner to the DB
     *
     * @param XeroResourceOwner $resourceOwner A resource owner
     *
     * @return void
     */
    public function saveResourceOwner(XeroResourceOwner $identity): ResourceOwner
    {
        $resourceOwner = new ResourceOwner(
            [
            'xeroUserId' => $identity->xero_userid,
            'preferredUsername' => $identity->preferred_username,
            'email' => $identity->email,
            'givenName' => $identity->given_name,
            'familyName' => $identity->family_name,
            ]
        );

        // Check if we already have a resource owner record
        $existingResourceOwner = ResourceOwner::find()->where(
            [
            'xeroUserId' => $identity->xero_userid
            ]
        )->one();

        if (!empty($existingResourceOwner)) {
            $resourceOwner->id = $existingResourceOwner->id;
            $resourceOwner->setIsNewRecord(false);
        }

        $resourceOwner->save();

        return $resourceOwner;
    }

    /**
     * Saves Xero credentials to the DB
     *
     * @param AccessTokenInterface $accessToken Xero access token
     *
     * @return Credential
     */
    public function saveCredential(Credential $credential): Credential
    {
        if ($credential->id) {
            $credential->setIsNewRecord(false);
        }

        $credential->save();

        return $credential;
    }

    /**
     * Saves a Xero Tenant to the DB
     *
     * @param XeroTenant $tenant Xero Tenant object
     *
     * @return Tenant
     */
    public function saveTenant(XeroTenant $tenant): Tenant
    {
        $tenantRecord = new Tenant(
            [
            'tenantId' => $tenant->tenantId,
            'tenantType' => $tenant->tenantType,
            'tenantName' => $tenant->tenantName,
            ]
        );

        $existingTenantRecord = Tenant::find()->where(
            [
            'tenantId' => $tenant->tenantId
            ]
        )->one();

        if (!empty($existingTenantRecord) ) {
            $tenantRecord->id = $existingTenantRecord->id;
            $tenantRecord->setIsNewRecord(false);
        }

        $tenantRecord->save();

        return $tenantRecord;
    }
}
