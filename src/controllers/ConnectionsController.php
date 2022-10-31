<?php
/**
 * Connections Controller
 *
 * Handles routing of tenant requests
 *
 * PHP version 7.4
 *
 * @category  Controllers
 * @package   CraftCommerceXero
 * @author    Josh Smith <by@joshthe.dev>
 * @copyright 2021 Josh Smith
 * @license   Proprietary https://github.com/thejoshsmith/commerce-xero/blob/master/LICENSE.md
 * @version   GIT: $Id$
 * @link      https://joshthe.dev
 * @since     1.0.0
 */

namespace thejoshsmith\commerce\xero\controllers;

use thejoshsmith\commerce\xero\Plugin;
use thejoshsmith\commerce\xero\records\Connection;

use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * Connections Controller
 */
class ConnectionsController extends BaseController
{
    /**
     * Updates a connection via a PATCH requets
     *
     * @return JSON
     */
    public function actionUpdate()
    {
        $this->_requirePatchRequest();

        $data = $this->request->getBodyParams();

        $connectionId = $data['connectionId'];
        $selected = $data['selected'];

        if (empty($connectionId) || !is_numeric($connectionId)) {
            throw new BadRequestHttpException('Connection ID is required.');
        }

        $connection = Plugin::getInstance()
            ->getXeroConnections()
            ->getConnectionById($connectionId);

        if (empty($connection)) {
            throw new NotFoundHttpException('Connection not found.');
        }

        // Update and save it
        $connection->scenario = Connection::SCENARIO_PATCH;
        $connection->attributes = $data;
        $connection->save();

        // Special case for selected connections
        if (isset($selected) && $selected === 1) {
            $connection = Plugin::getInstance()
                ->getXeroConnections()
                ->markAsSelected($connectionId);
        }

        return $this->asJson([
            'success' => true,
            'data' => $connection
        ]);
    }

    /**
     * Disconnects a connection
     *
     * @return JSON
     */
    public function actionDisconnect()
    {
        $this->requirePostRequest();
        $xeroConnections = Plugin::getInstance()->getXeroConnections();

        $connectionId = $this->request->getBodyParam('connectionId');

        if (empty($connectionId) || !is_numeric($connectionId)) {
            throw new BadRequestHttpException('Connection ID is required.');
        }

        $xeroConnections->disconnectFromXero($connectionId);

        // Attempt to mark another connection as the now selected one
        $connection = $xeroConnections->getLastCreatedOrUpdated();

        if ($connection) {
            $xeroConnections->markAsSelected($connection->id);
        }

        return $this->asJson([
            'success' => true
        ]);
    }

    private function _requirePatchRequest()
    {
        if (!$this->request->getIsPatch()) {
            throw new BadRequestHttpException('Patch request required');
        }
    }
}
