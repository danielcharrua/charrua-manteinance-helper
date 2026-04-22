/**
 * Build script: creates a clean plugin zip respecting .distignore.
 * Reads exclusion patterns from .distignore, then zips everything else.
 *
 * Usage: node scripts/build-zip.mjs
 */

import { execSync } from 'child_process';
import { readFileSync, existsSync, mkdirSync } from 'fs';
import { join, resolve } from 'path';

const ROOT = resolve( import.meta.dirname, '..' );
const PLUGIN_SLUG = 'charrua-maintenance-helper';
const OUT_DIR = join( ROOT, 'release' );
const OUT_FILE = join( OUT_DIR, `${ PLUGIN_SLUG }.zip` );
const DISTIGNORE = join( ROOT, '.distignore' );

// Parse .distignore: each non-empty, non-comment line is an exclusion pattern.
const ignorePatterns = readFileSync( DISTIGNORE, 'utf8' )
    .split( '\n' )
    .map( line => line.trim() )
    .filter( line => line && ! line.startsWith( '#' ) );

// Build zip exclusion flags. Each pattern gets a wildcard suffix to cover
// directory contents, and zip needs patterns relative to the working directory.
const exclusions = ignorePatterns
    .flatMap( pattern => [
        `--exclude=./${ pattern }`,
        `--exclude=./${ pattern }/*`,
        `--exclude=./${ pattern }/**`,
    ] )
    .join( ' ' );

// Always exclude the output zip itself to avoid recursion.
const selfExclude = `--exclude=./release/${ PLUGIN_SLUG }.zip`;

mkdirSync( OUT_DIR, { recursive: true } );

const cmd = `zip -r "${ OUT_FILE }" . ${ exclusions } ${ selfExclude }`;

console.log( `Building ${ PLUGIN_SLUG }.zip...` );

try {
    execSync( cmd, { cwd: ROOT, stdio: 'pipe' } );
    const stat = execSync( `du -sh "${ OUT_FILE }"`, { encoding: 'utf8' } ).trim().split( '\t' )[ 0 ];
    console.log( `Done. release/${ PLUGIN_SLUG }.zip (${ stat })` );
} catch ( err ) {
    console.error( 'Build failed:', err.message );
    process.exit( 1 );
}
