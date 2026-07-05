/**
 * Type definitions for the MMV beta viewer UI module.
 *
 * Runtime classes are imported and re-exported so that consumers only need
 * a single import source.  Only types that have no corresponding class
 * (like ViewerState) are defined here as interfaces.
 */

import 'types-mediawiki';
import { Ref } from 'vue';

import LightboxImage from '../mmv.bootstrap/mmv.lightboximage.js';
import ImageModel from '../mmv.common/model/mmv.model.Image.js';
export { LightboxImage, ImageModel };

/**
 * Reactive state shared between BetaViewer (imperative host) and the
 * Vue component tree via provide/inject.
 */
export interface ViewerState {
	image: Ref<LightboxImage | null>;
	imageInfo: Ref<ImageModel | null>;
	displayUrl: Ref<string>;
	thumbs: Ref<LightboxImage[]>;
	isOpen: Ref<boolean>;
	chromeVisible: Ref<boolean>;
}
