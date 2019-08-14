<?php

use EMailChef\Command\Api\GetAuthenticationTokenCommand;

class GetAuthenticationTokenCommandTest extends PHPUnit_Framework_TestCase
{
    /**
   * @group apicalls
   */
  public function testExecute()
  {
      $c = new GetAuthenticationTokenCommand();
      $result = $c->execute('info@nicolamoretti.com', 'W9FYihnmgTbU7t2J/');
      $this->assertInternalType('string', $result);
  }
}
