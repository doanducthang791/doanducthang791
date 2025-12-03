<?php

namespace Drupal\Tests\sprep_vl_module\Functional;

use Drupal\Core\Cache\Cache;
use Drupal\Tests\sprep_vl_module\Traits\FileUploadTrait;
use weitzman\DrupalTestTraits\Entity\UserCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;
use weitzman\DrupalTestTraits\ScreenShotTrait;

/**
 * Tests related to searching and search performance.
 *
 * @group custom
 */
class SearchTest extends ExistingSiteSelenium2DriverTestBase {
  use FileUploadTrait;
  use ScreenShotTrait;
  use UserCreationTrait;

  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->createUser([], 'testUser');
    $this->adminUser->addRole('library_admin');
    $this->adminUser->save();
    $this->drupalLogin($this->adminUser);
    $this->getSession()->resizeWindow(1920, 3000);
  }

  /**
   * Test speedy searching.
   */
  public function testTurboSearch() {
    // Create a document with a file uploaded to it.
    $this->visit('/node/add/ctr');
    $page = $this->getCurrentPage();

    $record_title = $this->randomMachineName();
    $abstract_text = $this->randomMachineName();

    $node = $this->createNode([
      'type' => 'ctr',
      'title' => $record_title,
      'field_ctr_abstract' => $abstract_text,
      'moderation_state' => 'published',
      'status' => 1,
    ]);

    $this->visit('/node/' . $node->id() . '/edit');

    // Index the node and wait for solr for a couple seconds.
    search_api_cron();
    sleep(3);

    $search_urls = [
      '/search',
      '/search?search_api_fulltext=' . $abstract_text,
      '/search/advanced',
      '/search/advanced?search_api_fulltext=' . $abstract_text,
    ];

    foreach ($search_urls as $search_url) {
      // Delete this entities cache.
      $bins = Cache::getBins();
      $bins['entity']->delete('values:node:' . $node->id());

      $this->visit($search_url);

      $rows = $page->findAll('css', 'a.search-content');
      $has_result = FALSE;
      foreach ($rows as $row) {
        if (strstr($row->getText(), $record_title)) {
          $has_result = TRUE;
          break;
        }
      }
      $this->assertTrue($has_result, "search result exists on " . $search_url);

      // Our node should NOT be in the cache at this point.
      $this->assertFalse($bins['entity']->get('values:node:' . $node->id()), "The node was loaded, this is bad for turbos on " . $search_url);
    }
  }

}
