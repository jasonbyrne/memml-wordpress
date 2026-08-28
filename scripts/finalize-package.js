const fs = require( 'fs' );
const path = require( 'path' );
const { spawnSync } = require( 'child_process' );

const projectRoot = path.resolve( __dirname, '..' );
const packageData = require( path.join( projectRoot, 'package.json' ) );
const source = path.join( projectRoot, packageData.name + '.zip' );
const outputDirectory = path.join( projectRoot, 'dist' );
const destination = path.join(
	outputDirectory,
	'memml-calendar-' + packageData.version + '.zip'
);
const pluginContents = fs.readFileSync(
	path.join( projectRoot, 'memml.php' ),
	'utf8'
);
const readmeContents = fs.readFileSync(
	path.join( projectRoot, 'readme.txt' ),
	'utf8'
);
const pluginVersion = pluginContents.match( /^ \* Version:\s+(.+)$/m );
const stableTag = readmeContents.match( /^Stable tag:\s+(.+)$/m );

if (
	! pluginVersion ||
	! stableTag ||
	pluginVersion[ 1 ].trim() !== packageData.version ||
	stableTag[ 1 ].trim() !== packageData.version
) {
	throw new Error(
		'Version mismatch: package.json, memml.php, and readme.txt must match.'
	);
}

if ( ! fs.existsSync( source ) ) {
	throw new Error( 'The WordPress plugin ZIP was not created: ' + source );
}

fs.mkdirSync( outputDirectory, { recursive: true } );
fs.rmSync( destination, { force: true } );
fs.renameSync( source, destination );

const removePackageManifest = spawnSync(
	'zip',
	[ '-d', destination, 'memml/package.json' ],
	{ stdio: 'inherit' }
);

if ( 0 !== removePackageManifest.status ) {
	throw new Error(
		'Could not remove development metadata from the archive.'
	);
}

const archiveList = spawnSync( 'unzip', [ '-Z1', destination ], {
	encoding: 'utf8',
} );

if ( 0 !== archiveList.status ) {
	throw new Error( 'Could not inspect the completed plugin archive.' );
}

const entries = archiveList.stdout.trim().split( '\n' );
const requiredEntries = [
	'memml/memml.php',
	'memml/readme.txt',
	'memml/build/index.js',
	'memml/build/index.asset.php',
];
const forbiddenPattern =
	/^memml\/(?:\.git|node_modules|package\.json|scripts|src|tests|vendor)(?:\/|$)/;

if (
	entries.some( ( entry ) => ! entry.startsWith( 'memml/' ) ) ||
	entries.some( ( entry ) => forbiddenPattern.test( entry ) ) ||
	requiredEntries.some( ( entry ) => ! entries.includes( entry ) )
) {
	throw new Error(
		'The completed archive is not an install-ready Memml plugin.'
	);
}

process.stdout.write( 'Install-ready plugin: ' + destination + '\n' );
