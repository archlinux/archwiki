<?php
/**
 * Mutable RevisionRecord implementation, for building new revision entries programmatically.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Revision;

use CommentStoreComment;
use Content;
use InvalidArgumentException;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Storage\RevisionSlotsUpdate;
use MediaWiki\User\UserIdentity;
use MWException;
use MWTimestamp;

/**
 * Mutable RevisionRecord implementation, for building new revision entries programmatically.
 * Provides setters for all fields.
 *
 * @newable
 *
 * @since 1.31
 * @since 1.32 Renamed from MediaWiki\Storage\MutableRevisionRecord
 * @property MutableRevisionSlots $mSlots
 */
class MutableRevisionRecord extends RevisionRecord {

	/**
	 * Returns an incomplete MutableRevisionRecord which uses $parent as its
	 * parent revision, and inherits all slots form it. If saved unchanged,
	 * the new revision will act as a null-revision.
	 *
	 * @param RevisionRecord $parent
	 *
	 * @return MutableRevisionRecord
	 */
	public static function newFromParentRevision( RevisionRecord $parent ) {
		$rev = new MutableRevisionRecord( $parent->getPage(), $parent->getWikiId() );

		foreach ( $parent->getSlotRoles() as $role ) {
			$slot = $parent->getSlot( $role, self::RAW );
			$rev->inheritSlot( $slot );
		}

		$rev->setPageId( $parent->getPageId() );
		$rev->setParentId( $parent->getId() );

		return $rev;
	}

	/**
	 * Returns a MutableRevisionRecord which is an updated version of $revision with $slots
	 * added.
	 * @param RevisionRecord $revision
	 * @param SlotRecord[] $slots
	 * @return MutableRevisionRecord
	 * @since 1.36
	 */
	public static function newUpdatedRevisionRecord(
		RevisionRecord $revision,
		array $slots
	): MutableRevisionRecord {
		$newRevisionRecord = new MutableRevisionRecord(
			$revision->getPage(),
			$revision->getWikiId()
		);

		$newRevisionRecord->setId( $revision->getId( $revision->getWikiId() ) );
		$newRevisionRecord->setPageId( $revision->getPageId( $revision->getWikiId() ) );
		$newRevisionRecord->setParentId( $revision->getParentId( $revision->getWikiId() ) );
		$newRevisionRecord->setUser( $revision->getUser() );

		foreach ( $revision->getSlots()->getSlots() as $role => $slot ) {
			$newRevisionRecord->setSlot( $slot );
		}

		foreach ( $slots as $role => $slot ) {
			$newRevisionRecord->setSlot( $slot );
		}

		return $newRevisionRecord;
	}

	/**
	 * @stable to call.
	 *
	 * @param PageIdentity $page The page this RevisionRecord is associated with.
	 * @param false|string $wikiId Relevant wiki id or self::LOCAL for the current one.
	 *
	 * @throws MWException
	 */
	public function __construct( PageIdentity $page, $wikiId = self::LOCAL ) {
		$slots = new MutableRevisionSlots( [], function () {
			$this->resetAggregateValues();
		} );

		parent::__construct( $page, $slots, $wikiId );
	}

	/**
	 * @param int $parentId
	 * @return self
	 */
	public function setParentId( int $parentId ) {
		$this->mParentId = $parentId;

		return $this;
	}

	/**
	 * Sets the given slot. If a slot with the same role is already present in the revision,
	 * it is replaced.
	 *
	 * @note This can only be used with a fresh "unattached" SlotRecord. Calling code that has a
	 * SlotRecord from another revision should use inheritSlot(). Calling code that has access to
	 * a Content object can use setContent().
	 *
	 * @note This may cause the slot meta-data for the revision to be lazy-loaded.
	 *
	 * @note Calling this method will cause the revision size and hash to be re-calculated upon
	 *       the next call to getSize() and getSha1(), respectively.
	 *
	 * @param SlotRecord $slot
	 * @return self
	 */
	public function setSlot( SlotRecord $slot ) {
		if ( $slot->hasRevision() && $slot->getRevision() !== $this->getId() ) {
			throw new InvalidArgumentException(
				'The given slot must be an unsaved, unattached one. '
				. 'This slot is already attached to revision ' . $slot->getRevision() . '. '
				. 'Use inheritSlot() instead to preserve a slot from a previous revision.'
			);
		}

		$this->mSlots->setSlot( $slot );

		return $this;
	}

	/**
	 * "Inherits" the given slot's content.
	 *
	 * If a slot with the same role is already present in the revision, it is replaced.
	 *
	 * @note This may cause the slot meta-data for the revision to be lazy-loaded.
	 *
	 * @param SlotRecord $parentSlot
	 * @return self
	 */
	public function inheritSlot( SlotRecord $parentSlot ) {
		$this->mSlots->inheritSlot( $parentSlot );

		return $this;
	}

	/**
	 * Sets the content for the slot with the given role.
	 *
	 * If a slot with the same role is already present in the revision, it is replaced.
	 * Calling code that has access to a SlotRecord can use inheritSlot() instead.
	 *
	 * @note This may cause the slot meta-data for the revision to be lazy-loaded.
	 *
	 * @note Calling this method will cause the revision size and hash to be re-calculated upon
	 *       the next call to getSize() and getSha1(), respectively.
	 *
	 * @param string $role
	 * @param Content $content
	 * @return self
	 */
	public function setContent( $role, Content $content ) {
		$this->mSlots->setContent( $role, $content );

		return $this;
	}

	/**
	 * Removes the slot with the given role from this revision.
	 * This effectively ends the "stream" with that role on the revision's page.
	 * Future revisions will no longer inherit this slot, unless it is added back explicitly.
	 *
	 * @note This may cause the slot meta-data for the revision to be lazy-loaded.
	 *
	 * @note Calling this method will cause the revision size and hash to be re-calculated upon
	 *       the next call to getSize() and getSha1(), respectively.
	 *
	 * @param string $role
	 * @return self
	 */
	public function removeSlot( $role ) {
		$this->mSlots->removeSlot( $role );

		return $this;
	}

	/**
	 * Applies the given update to the slots of this revision.
	 *
	 * @param RevisionSlotsUpdate $update
	 * @return self
	 */
	public function applyUpdate( RevisionSlotsUpdate $update ) {
		$update->apply( $this->mSlots );

		return $this;
	}

	/**
	 * @param CommentStoreComment $comment
	 * @return self
	 */
	public function setComment( CommentStoreComment $comment ) {
		$this->mComment = $comment;

		return $this;
	}

	/**
	 * Set revision hash, for optimization. Prevents getSha1() from re-calculating the hash.
	 *
	 * @note This should only be used if the calling code is sure that the given hash is correct
	 * for the revision's content, and there is no chance of the content being manipulated
	 * later. When in doubt, this method should not be called.
	 *
	 * @param string $sha1 SHA1 hash as a base36 string.
	 * @return self
	 */
	public function setSha1( string $sha1 ) {
		$this->mSha1 = $sha1;

		return $this;
	}

	/**
	 * Set nominal revision size, for optimization. Prevents getSize() from re-calculating the size.
	 *
	 * @note This should only be used if the calling code is sure that the given size is correct
	 * for the revision's content, and there is no chance of the content being manipulated
	 * later. When in doubt, this method should not be called.
	 *
	 * @param int $size nominal size in bogo-bytes
	 * @return self
	 */
	public function setSize( int $size ) {
		$this->mSize = $size;

		return $this;
	}

	/**
	 * @param int $visibility
	 * @return self
	 */
	public function setVisibility( int $visibility ) {
		$this->mDeleted = $visibility;

		return $this;
	}

	/**
	 * @param string $timestamp A timestamp understood by MWTimestamp
	 * @return self
	 */
	public function setTimestamp( string $timestamp ) {
		$this->mTimestamp = MWTimestamp::convert( TS_MW, $timestamp );

		return $this;
	}

	/**
	 * @param bool $minorEdit
	 * @return self
	 */
	public function setMinorEdit( bool $minorEdit ) {
		$this->mMinorEdit = $minorEdit;

		return $this;
	}

	/**
	 * Set the revision ID.
	 *
	 * MCR migration note: this replaced Revision::setId
	 *
	 * @warning Use this with care, especially when preparing a revision for insertion
	 *          into the database! The revision ID should only be fixed in special cases
	 *          like preserving the original ID when restoring a revision.
	 *
	 * @param int $id
	 * @return self
	 */
	public function setId( int $id ) {
		$this->mId = $id;

		return $this;
	}

	/**
	 * Sets the user identity associated with the revision
	 *
	 * @param UserIdentity $user
	 * @return self
	 */
	public function setUser( UserIdentity $user ) {
		$this->mUser = $user;

		return $this;
	}

	/**
	 * @param int $pageId
	 * @return self
	 */
	public function setPageId( int $pageId ) {
		$pageIdBasedOnPage = $this->getArticleId( $this->mPage );
		if ( $pageIdBasedOnPage && $pageIdBasedOnPage !== $this->getArticleId( $this->mPage ) ) {
			throw new InvalidArgumentException(
				'The given page does not belong to page ID ' . $this->mPageId
			);
		}

		$this->mPageId = $pageId;

		return $this;
	}

	/**
	 * Returns the nominal size of this revision.
	 *
	 * MCR migration note: this replaced Revision::getSize
	 *
	 * @return int The nominal size, may be computed on the fly if not yet known.
	 */
	public function getSize() {
		// If not known, re-calculate and remember. Will be reset when slots change.
		if ( $this->mSize === null ) {
			$this->mSize = $this->mSlots->computeSize();
		}

		return $this->mSize;
	}

	/**
	 * Returns the base36 sha1 of this revision.
	 *
	 * MCR migration note: this replaced Revision::getSha1
	 *
	 * @return string The revision hash, may be computed on the fly if not yet known.
	 */
	public function getSha1() {
		// If not known, re-calculate and remember. Will be reset when slots change.
		if ( $this->mSha1 === null ) {
			$this->mSha1 = $this->mSlots->computeSha1();
		}

		return $this->mSha1;
	}

	/**
	 * Returns the slots defined for this revision as a MutableRevisionSlots instance,
	 * which can be modified to defined the slots for this revision.
	 *
	 * @return MutableRevisionSlots
	 */
	public function getSlots(): RevisionSlots {
		// Overwritten just guarantee the more narrow return type.
		// TODO Update return typehint once full return type covariance is allowed (PHP 7.4+, T278139)
		// @phan-suppress-next-line PhanTypeMismatchReturnSuperType
		return parent::getSlots();
	}

	/**
	 * Invalidate cached aggregate values such as hash and size.
	 * Used as a callback by MutableRevisionSlots.
	 */
	private function resetAggregateValues() {
		$this->mSize = null;
		$this->mSha1 = null;
	}

}
