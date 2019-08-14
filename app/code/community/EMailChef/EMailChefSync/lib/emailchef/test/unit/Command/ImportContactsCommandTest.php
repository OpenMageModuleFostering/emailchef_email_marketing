  <?php

use EMailChef\Command\Api\ImportContactsCommand;

class ImportContactsCommandTest extends PHPUnit_Framework_TestCase
{
    /**
   * @group apicalls
   */
  public function testExecute()
  {
      $c = new ImportContactsCommand();
      $contacts = array(array(
      'first_name' => 'nicola',
      'last_name' => 'm',
      'email' => 'nicolamti@gmail.com',
      'company_name' => 'nmtest',
      'test' => 'ntestm',
    ), array(
      'first_name' => 'nicola2',
      'last_name' => 'm2',
      'email' => 'nicola.mti@gmail.com',
      'company_name' => 'nmtest2',
      'test' => 'ntestm2',
    ));
      $listId = 452;
      $result = $c->execute('9fae370a9a0768401a4837b8ab96f43aa7d885d4', $contacts, $listId);
      $this->assertTrue($result);
  }
}
