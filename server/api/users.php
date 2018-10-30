<?php

/**
 * User API.
 *
 * Provides user informations
 *
 * @version 1.0.0
 *
 * @api
 */
require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/Api.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/User.php';
$api = new Api('json', ['GET', 'POST', 'PUT']);
switch ($api->method) {
  case 'GET':
      //returns a specific user or all
      if (!$api->checkAuth()) {
          //User not authentified/authorized
          return;
      }
      if ($api->checkParameterExists('id', $id) && $id !== '') {
          //request a specific user
          if ($api->requesterId !== intval($id) && !$api->checkScope('admin')) {
              $api->output(403, 'Admin scope required');
              //indicate the requester is not the user and is not allowed to update it
              return;
          }
          $user = new User($id);
          if (!$user->get()) {
              $api->output(404, 'User not found');
              //indicate the user was not found
              return;
          }
          $api->output(200, $user->getProfile());
          //return requested user
          return;
      }
      //request all users
      if (!$api->checkScope('admin')) {
          $api->output(403, 'Admin scope required');
          //indicate the requester is not allowed to get all users
          return;
      }
      $rawUsers = User::getAll();
      $users =  array();
      foreach ($rawUsers as $user) {
          array_push($users, $user->getProfile());
      }
      $api->output(200, $users);
      break;

    case 'POST':
        //create user
        if (!$api->checkAuth()) {
            //User not authentified/authorized
            return;
        }
        if (!$api->checkScope('admin')) {
            $api->output(403, 'Admin scope required');
            //indicate the requester is not allowed to create user
            return;
        }
        $postedUser = new User();

        if (!$api->checkParameterExists('login', $postedUser->login) || $postedUser->login == '') {
            $api->output(400, 'Login must be provided');
            //indicate the request is not valid, login must be provided
            return;
        }
        if (!$api->checkParameterExists('password', $postedUser->password) || $postedUser->password == '') {
            $api->output(400, 'Password must be provided');
            //indicate the request is not valid, password must be provided
            return;
        }
        if (!$api->checkParameterExists('mail', $postedUser->mail) || $postedUser->mail == '') {
            $api->output(400, 'Mail address must be provided');
            //indicate the request is not valid, mail must be provided
            return;
        }
        $api->checkParameterExists('scope', $postedUser->scope);
        if ($api->checkParameterExists('status', $postedUser->status)) {
            $postedUser->status = boolval($postedUser->status);
        }

        if (!$postedUser->insert($error)) {
            $api->output(500, 'Error during user creation'.$error);
            //something gone wrong :(
            return;
        }
        $postedUser->get();
        $api->output(201, $postedUser->getProfile());
        break;

    case 'PUT':
        //update user
        if (!$api->checkAuth()) {
            //User not authentified/authorized
            return;
        }
        $postedUser = new User();
        if (!$api->checkParameterExists('id', $postedUser->id) || $postedUser->id == '') {
            $api->output(400, 'PUT method must be called on a specific resource');
            //indicate the request is not valid, id must be provided in query path
            return;
        }
        $postedUser->id = intval($postedUser->id);
        if ($postedUser->id !== $api->requesterId && !$api->checkScope('admin')) {
            $api->output(403, 'User can be updated by himself only');
            //indicate the requester is not the user and is not allowed to update it
            return;
        }
        if (!$api->checkParameterExists('login', $postedUser->login) || $postedUser->login == '') {
            $api->output(400, 'Login must be provided');
            //indicate the request is not valid, login must be provided
            return;
        }
        if ($api->checkParameterExists('password', $postedUser->password) && $postedUser->password == '') {
            $api->output(400, 'Password must be provided');
            //indicate the request is not valid, password must be provided
            return;
        }
        if ($api->checkParameterExists('mail', $postedUser->mail) && $postedUser->mail == '') {
            $api->output(400, 'Mail address must be provided');
            //indicate the request is not valid, mail must be provided
            return;
        }
        $api->checkParameterExists('scope', $postedUser->scope);
        if ($api->checkParameterExists('status', $postedUser->status)) {
            $postedUser->status = boolval($postedUser->status);
        }
        $user = new User($postedUser->id);
        if (!$user->get()) {
            $api->output(404, 'User not found');
            //indicate the user was not found
            return;
        }
        //iterate on each object attributes to set object
        foreach ($user as $key => $value) {
            if (property_exists($postedUser, $key) && !is_null($postedUser->$key)) {
                //get provided attribute
                $user->$key = $postedUser->$key;
            }
        }
        if (!$user->update($error)) {
            $api->output(500, 'Error during profile update'.$error);
            //something gone wrong :(
            return;
        }
        if (!is_null($postedUser->password) && !$user->updatePassword($error)) {
            $api->output(500, 'Error during password update'.$error);
            //something gone wrong :(
            return;
        }
        $user->get();
        $api->output(200, $user->getProfile());
        break;
}
