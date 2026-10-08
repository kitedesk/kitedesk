import { domMax } from 'framer-motion';

/**
 * Animation features for <LazyMotion>, loaded after the first render so framer-motion's
 * engine stays out of the entry bundle. domMax (not domAnimation) because some components
 * use layout / layoutId animations.
 */
export default domMax;
