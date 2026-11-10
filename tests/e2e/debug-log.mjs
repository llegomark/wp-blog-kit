/**
 * What the local site wrote to wp-content/debug.log during a run of the browser tests. Under
 * WP-CLI, PHP's notices and warnings go only to that log, and no page shows them; a run that leaves
 * one of the kit's there has not passed.
 *
 * Nothing is emptied or deleted: the size at the start marks where the run's lines begin.
 */
import { closeSync, openSync, readSync, statSync } from 'node:fs';

/** A line is the kit's when it names one of its folders, text domains or function prefixes. */
const KIT = /tala[-_](?:seo|ads|guard|blog)/i;

/**
 * The log's size in bytes; 0 when there is no log.
 *
 * @param {string} file Path of debug.log.
 * @return {number} Bytes.
 */
export function logSize( file ) {
	try {
		return statSync( file ).size;
	} catch {
		return 0;
	}
}

/**
 * The lines written after byte `from` that name the kit. A log that is shorter than `from` was
 * emptied or replaced since, so all of it is new.
 *
 * @param {string} file Path of debug.log.
 * @param {number} from The log's size when the run started.
 * @return {string[]} Lines.
 */
export function kitLines( file, from ) {
	const size = logSize( file );
	if ( ! size ) {
		return [];
	}
	const start = size < from ? 0 : from;
	const buffer = Buffer.alloc( size - start );
	const handle = openSync( file, 'r' );
	try {
		readSync( handle, buffer, 0, buffer.length, start );
	} finally {
		closeSync( handle );
	}
	return buffer
		.toString( 'utf8' )
		.split( '\n' )
		.filter( ( line ) => KIT.test( line ) );
}
