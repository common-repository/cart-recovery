module.exports = function(grunt) {

	// Project configuration.
	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),
		less: {
			development: {
				options: {
					paths: ["css"],
					compress: true,
				},
				files: {
					"css/cart-recovery-for-wordpress-admin.css": "less/cart-recovery-for-wordpress-admin.less"
				}
			}
		},
		uglify: {
			crfwjs: {
				files: {
			        'js/crfw-edd.min.js': ['js/crfw-edd.js'],
			        'js/crfw-rcp.min.js': ['js/crfw-rcp.js'],
			        'js/crfw-woocommerce.min.js': ['js/crfw-woocommerce.js'],
			        'js/crfw-wpecommerce.min.js': ['js/crfw-wpecommerce.js'],
			        'js/cart-recovery-for-wordpress.min.js': ['js/cart-recovery-for-wordpress.js'],
			        'js/tippy.all.min.js': ['node_modules/tippy.js/dist/tippy.all.min.js']
			    }
			}
		},
		watch: {
			css: {
				files: [
					'less/cart-recovery-for-wordpress-admin.less'
				],
				tasks: [ 'less' ],
			},
			js: {
				files: [
					'js/cart-recovery-for-wordpress.js',
					'js/crfw-edd.js',
					'js/crfw-woocommerce.js',
					'js/crfw-wpecommerce.js',
					'node_modules/tippy.js/dist/tippy.all.min.js'
				],
				tasks: [ 'uglify' ]
			},
		},
		makepot: {
			target: {
				options: {
					cwd: '',                          // Directory of files to internationalize.
					domainPath: 'languages/',         // Where to save the POT file.
					exclude: ['vendor'],                      // List of files or directories to ignore.
					include: [],                      // List of files or directories to include.
					mainFile: 'cart-recovery-for-wordpress.php',                     // Main project file.
					potComments: 'Copyright (c) 2020 Ademti Software Ltd.',                  // The copyright at the beginning of the POT file.
					potFilename: 'cart-recovery.pot',                  // Name of the POT file.
					potHeaders: {
						poedit: false,                 // Includes common Poedit headers.
						'x-poedit-keywordslist': true, // Include a list of all possible gettext functions.
						'Language-Team': '"WP Cart Recovery Support" <support@wp-cart-recovery.com>',
						'Last-Translator': '"WP Cart Recovery Support" <support@wp-cart-recovery.com>',
						'Report-Msgid-Bugs-To': 'https://wp-cart-recovery.com/support-request/\n'
					},                                // Headers to add to the generated POT file.
					processPot: null,                 // A callback function for manipulating the POT file.
					type: 'wp-plugin',                // Type of project (wp-plugin or wp-theme).
					updateTimestamp: true,            // Whether the POT-Creation-Date should be updated without other changes.
					updatePoFiles: false              // Whether to update PO files in the same directory as the POT file.
				}
			}
		}
	});

	grunt.loadNpmTasks('grunt-contrib-less');
	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-contrib-watch');
	grunt.loadNpmTasks('grunt-wp-i18n');

	// Default task(s).
	grunt.registerTask('default', ['less', 'uglify', 'makepot']);

};
