<?php declare(strict_types=1);

/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2024
 */

namespace OCA\Music\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

use OCA\Music\AppFramework\BusinessLayer\BusinessLayerException;
use OCA\Music\AppFramework\Core\Logger;
use OCA\Music\BusinessLayer\AlbumBusinessLayer;
use OCA\Music\BusinessLayer\ArtistBusinessLayer;
use OCA\Music\BusinessLayer\BookmarkBusinessLayer;
use OCA\Music\BusinessLayer\GenreBusinessLayer;
use OCA\Music\BusinessLayer\PlaylistBusinessLayer;
use OCA\Music\BusinessLayer\PodcastChannelBusinessLayer;
use OCA\Music\BusinessLayer\PodcastEpisodeBusinessLayer;
use OCA\Music\BusinessLayer\RadioStationBusinessLayer;
use OCA\Music\BusinessLayer\TrackBusinessLayer;
use OCA\Music\Db\SortBy;
use OCA\Music\Http\ErrorResponse;
use OCA\Music\Utility\Random;
use OCA\Music\Utility\Util;

class AdvSearchController extends Controller {

	private $albumBusinessLayer;
	private $artistBusinessLayer;
	private $bookmarkBusinessLayer;
	private $genreBusinessLayer;
	private $playlistBusinessLayer;
	private $podcastChannelBusinessLayer;
	private $podcastEpisodeBusinessLayer;
	private $radioStationBusinessLayer;
	private $trackBusinessLayer;
	private $userId;
	private $random;
	private $logger;

	public function __construct(string $appName,
								IRequest $request,
								AlbumBusinessLayer $albumBusinessLayer,
								ArtistBusinessLayer $artistBusinessLayer,
								BookmarkBusinessLayer $bookmarkBusinessLayer,
								GenreBusinessLayer $genreBusinessLayer,
								PlaylistBusinessLayer $playlistBusinessLayer,
								PodcastChannelBusinessLayer $podcastChannelBusinessLayer,
								PodcastEpisodeBusinessLayer $podcastEpisodeBusinessLayer,
								RadioStationBusinessLayer $radioStationBusinessLayer,
								TrackBusinessLayer $trackBusinessLayer,
								?string $userId, // null if this gets called after the user has logged out or on a public page
								Random $random,
								Logger $logger) {
		parent::__construct($appName, $request);
		$this->albumBusinessLayer = $albumBusinessLayer;
		$this->artistBusinessLayer = $artistBusinessLayer;
		$this->bookmarkBusinessLayer = $bookmarkBusinessLayer;
		$this->genreBusinessLayer = $genreBusinessLayer;
		$this->playlistBusinessLayer = $playlistBusinessLayer;
		$this->podcastChannelBusinessLayer = $podcastChannelBusinessLayer;
		$this->podcastEpisodeBusinessLayer = $podcastEpisodeBusinessLayer;
		$this->radioStationBusinessLayer = $radioStationBusinessLayer;
		$this->trackBusinessLayer = $trackBusinessLayer;
		$this->userId = $userId;
		$this->random = $random;
		$this->logger = $logger;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function search(string $entity, string $rules, string $conjunction='and', string $order='name', ?int $limit=null, ?int $offset=null) {
		$rules = \json_decode($rules, true);

		foreach ($rules as $rule) {
			if (empty($rule['rule'] || empty($rule['operator'] || !isset($rule['input'])))) {
				return new ErrorResponse(Http::STATUS_BAD_REQUEST, 'Invalid search rule');
			}
		}

		try {
			$businessLayer = $this->businessLayerForType($entity);

			if ($businessLayer !== null) {
				$entities = $businessLayer->findAllAdvanced(
					$conjunction, $rules, $this->userId, self::mapSortBy($order), ($order==='random') ? $this->random : null, $limit, $offset);
				$entityIds = Util::extractIds($entities);
				return new JSONResponse([
					'id' => \md5($entity.\serialize($entityIds)), // use hash => identical results will have identical ID
					Util::snakeToCamelCase($entity).'Ids' => $entityIds
				]);
			} else {
				return new ErrorResponse(Http::STATUS_BAD_REQUEST, "Entity type '$entity' is not supported");
			}
		} catch (BusinessLayerException $e) {
			return new ErrorResponse(Http::STATUS_BAD_REQUEST, $e->getMessage());
		}
	}

	private function businessLayerForType($type) {
		$map = [
			'album' => $this->albumBusinessLayer,
			'artist' => $this->artistBusinessLayer,
			'bookmark' => $this->bookmarkBusinessLayer,
			'genre' => $this->genreBusinessLayer,
			'playlist' => $this->playlistBusinessLayer,
			'podcast_channel' => $this->podcastChannelBusinessLayer,
			'podcast_episode' => $this->podcastEpisodeBusinessLayer,
			'radio' => $this->radioStationBusinessLayer,
			'track' => $this->trackBusinessLayer,
		];
		return $map[$type] ?? null;
	}

	private static function mapSortBy(string $order) {
		$map = [
			'name'			=> SortBy::Name,
			'parent'		=> SortBy::Parent,
			'newest'		=> SortBy::Newest,
			'play_count'	=> SortBy::PlayCount,
			'last_played'	=> SortBy::LastPlayed,
			'rating'		=> SortBy::Rating,
		];
		return $map[$order] ?? SortBy::Name;
	}

}