  <?php

use EMailChef\Command\Api\AddEmailsToGroupCommand;

class AddEmailsToGroupCommandTest extends PHPUnit_Framework_TestCase
{
    /**
   * @group apicalls
   */
  public function testExecute()
  {
      $c = new AddEmailsToGroupCommand();
      $emails = array('nicolamti@gmail.com', 'nicolam.ti@gmail.com');
      $listId = 1254;
      $groupId = 204;
      $result = $c->execute('850251f2699d2df00a66486739b1c2bf3130b049', $emails, $listId, $groupId);
      $this->assertInternalType('string', $result);
  }
}
