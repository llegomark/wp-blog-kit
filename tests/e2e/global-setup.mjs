/**
 * Around the whole run of the browser tests: the site's debug.log must gain no line that names the
 * kit (tests/e2e/debug-log.mjs). The tests drive the site through the browser and through WP-CLI,
 * and WP-CLI's notices reach nothing but that log.
 */
import { join } from 'node:path';
import { kitLines, logSize } from './debug-log.mjs';
import { SITE } from './utils.mjs';

export default function globalSetup() {
	const log = join( SITE, 'wp-content', 'debug.log' );
	const from = logSize( log );
	return () => {
		const lines = kitLines( log, from );
		if ( lines.length ) {
			throw new Error(
				`${ log } gained ${ lines.length } line(s) that name the kit during this run:\n\n${ lines.slice( 0, 20 ).join( '\n' ) }${ lines.length > 20 ? '\n…' : '' }`,
			);
		}
	};
}
