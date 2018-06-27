<?php

/**
 * account API.
 *
 * Provides account informations
 *
 * @version 1.0.0
 *
 * @api
 */
require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/Api.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/Account.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/User.php';
$api = new Api('json', ['GET', 'POST', 'DELETE']);
switch ($api->method) {
    case 'GET':
        //returns the account
        if (!$api->checkAuth()) {
            //User not authentified/authorized
            return;
        }
        if (!$api->checkParameterExists('id', $id)) {
            //query all user's account
            $user = new User($api->requesterId);
            $accounts = array();
            foreach ($user->getAccounts() as $account) {
                array_push($accounts, $account->structureData());
            }
            $api->output(200, $accounts);
            //return user accounts list
            return;
        }
        $account = new Account($id);
        //query a specific account
        if (!$account->get()) {
            $api->output(404, 'Account not found');
            //indicate the account was not found
            return;
        }
        if ($account->user !== $api->requesterId) {
            $api->output(403, 'Transactions can be queried by account owner only');
            //indicate the requester is not the account owner and is not allowed to query it
            return;
        }
        $api->output(200, $account->structureData());
        break;
    case 'POST':
        //account creation
        if (!$api->checkAuth()) {
            //User not authentified/authorized
            return;
        }
        //@TODO add controls
        $account = new Account();
        $account->user = $api->requesterId;
        $api->checkParameterExists('bankId', $account->bankId);
        $api->checkParameterExists('branchId', $account->branchId);
        $api->checkParameterExists('accountId', $account->accountId);
        if ($account->getByPublicId($account->user, $account->bankId, $account->branchId, $account->accountId)) {
            $api->output(400, 'This account already exists');
            return;
        }
        if ($account->insert()) {
            $api->output(201, $account->structureData());
            return;
        }
        $api->output(500, 'Something went wrong');
        break;
    case 'DELETE':
        //account deletion
        if (!$api->checkAuth()) {
            //User not authentified/authorized
            return;
        }
        if (!$api->checkParameterExists('id', $id)) {
            $api->output(400, 'Account id must be provided');
            //indicate the request is not valid
            return;
        }
        $account = new Account($id);
        if (!$account->get()) {
            $api->output(404, 'Account not found');
            //indicate the account was not found
            return;
        }
        if ($account->user !== $api->requesterId) {
            $api->output(403, 'Account can be deleted by owner only');
            //indicate the requester is not the account owner and is not allowed to delete it
            return;
        }
        if ($account->delete()) {
            $api->output(204);
            return;
        }
        $api->output(500, 'Something went wrong');
        break;
}