<?php

/*
 * Looking Glass - An easy to deploy Looking Glass
 * Copyright (C) 2014-2019 Guillaume Mazoyer <gmazoyer@gravitons.in>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301  USA
 */

require_once('Crypt/RSA.php');
require_once('Net/SSH2.php');
require_once('authentication.php');
require_once('includes/utils.php');

final class SSH extends Authentication {
  private $port;
  private $m2m;

  public function __construct($config, $debug) {
    parent::__construct($config, $debug);

    $this->m2m = isset($this->config['m2m_command']) ? (string) $this->config['m2m_command'] : NULL;
    $this->port = isset($this->config['port']) ? (int) $this->config['port'] : 22;

    if ($this->debug) {
      define('NET_SSH2_LOGGING', NET_SSH2_LOG_COMPLEX);
    }
  }

  protected function check_config() {
    if ($this->config['auth'] == 'ssh-password') {
      if (!isset($this->config['user']) || !isset($this->config['pass'])) {
        throw new Exception('Router authentication configuration incomplete.');
      }
    }

    if ($this->config['auth'] == 'ssh-key') {
      if (!isset($this->config['user']) || !isset($this->config['private_key'])) {
        throw new Exception('Router authentication configuration incomplete.');
      }

      if (isset($this->config['private_key']) && !is_readable($this->config['private_key'])) {
        throw new Exception('SSH key for authentication is not readable.');
      }
    }
  }

  public function connect() {
    $this->connection = new Net_SSH2($this->config['host'], $this->port);
    $this->connection->setTimeout($this->config['timeout']);
    $success = false;

    if ($this->config['auth'] == 'ssh-password') {
      $success = $this->connection->login($this->config['user'], $this->config['pass']);
    } else if ($this->config['auth'] == 'ssh-key') {
      $key = new Crypt_RSA();

      if (isset($this->config['pass'])) {
        $key->setPassword($this->config['pass']);
      }
      $key->loadKey(file_get_contents($this->config['private_key']));

      $success = $this->connection->login($this->config['user'], $key);
    } else {
      throw new Exception('Unknown type of connection.');
    }

    if ($this->debug) {
      log_to_file($this->connection->getLog());
    }

    if (!$success) {
      throw new Exception('Cannot connect to router.');
    }
  }

  public function send_command($command) {
    $this->connect();

    //Machine-To-Machine (M2M) or (No-Pagging)
    if ($this->m2m !== NULL){
      $message = $this->connection->exec($this->m2m);
    }
    $this->connection->read();
    $data = $this->connection->exec($command);
    if ($this->debug) {
      log_to_file($this->connection->getLog());
    }

    $this->disconnect();

    return $data;
  }

  public function disconnect() {
    if (($this->connection != null) && $this->connection->isConnected()) {
      $this->connection->disconnect();

      if ($this->debug) {
        log_to_file($this->connection->getLog());
      }

      $this->connection = null;
    }
  }
}

// End of ssh.php
