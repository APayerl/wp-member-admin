#!/usr/bin/env bash

if [ $# -lt 3 ]; then
	echo "Användning: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress/}

download() {
    if [ `which curl` ]; then
        curl -s "$1" > "$2";
    elif [ `which wget` ]; then
        wget -nv -O "$2" "$1"
    fi
}

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
	WP_TESTS_TAG="branches/$WP_VERSION"
elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
	if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
		# Version x.x.0 behöver specialhantering
		WP_TESTS_TAG="branches/${WP_VERSION%??}"
	else
		WP_TESTS_TAG="tags/$WP_VERSION"
	fi
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
	WP_TESTS_TAG="trunk"
else
	# http://develop.svn.wordpress.org/trunk
	WP_TESTS_TAG="trunk"
fi

set -ex

install_wp() {

	if [ -d $WP_CORE_DIR ]; then
		return;
	fi

	mkdir -p $WP_CORE_DIR

	if [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
		mkdir -p $TMPDIR/wordpress-nightly
		download https://wordpress.org/nightly-builds/wordpress-latest.zip  $TMPDIR/wordpress-nightly/wordpress-nightly.zip
		unzip -q $TMPDIR/wordpress-nightly/wordpress-nightly.zip -d $TMPDIR/wordpress-nightly/
		mv $TMPDIR/wordpress-nightly/wordpress/* $WP_CORE_DIR
	else
		if [ $WP_VERSION == 'latest' ]; then
			local ARCHIVE_NAME='latest'
		elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+ ]]; then
			# https://wordpress.org/wordpress-x.x.tar.gz
			local ARCHIVE_NAME="wordpress-$WP_VERSION"
		else
			# https://wordpress.org/wordpress-x.x.x.tar.gz
			local ARCHIVE_NAME="wordpress-$WP_VERSION"
		fi
		download https://wordpress.org/${ARCHIVE_NAME}.tar.gz  $TMPDIR/wordpress.tar.gz
		tar --strip-components=1 -zxmf $TMPDIR/wordpress.tar.gz -C $WP_CORE_DIR
	fi

	download https://raw.github.com/markoheijnen/wp-mysqli/master/db.php $WP_CORE_DIR/wp-content/db.php
}

install_test_suite() {
	# Portable in-place argument replacements via sed
	local ioption='-i'
	if [[ "$OSTYPE" == "darwin"* ]]; then
		ioption='-i .bak'
	fi

	# Sätt upp tests directory
	mkdir -p $WP_TESTS_DIR

	if [ ! -f wp-tests-config.php ]; then
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php "$WP_TESTS_DIR"/wp-tests-config.php
		# Ersätt placeholder med riktiga databas-credentials
		sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR':" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR"/wp-tests-config.php
	fi

	if [ ! -d "$WP_TESTS_DIR"/includes ]; then
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/functions.php "$WP_TESTS_DIR"/includes/functions.php
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/install.php "$WP_TESTS_DIR"/includes/install.php
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/bootstrap.php "$WP_TESTS_DIR"/includes/bootstrap.php
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/listener-loader.php "$WP_TESTS_DIR"/includes/listener-loader.php
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/testcase.php "$WP_TESTS_DIR"/includes/testcase.php
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/testcase-rest-api.php "$WP_TESTS_DIR"/includes/testcase-rest-api.php
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/testcase-rest-controller.php "$WP_TESTS_DIR"/includes/testcase-rest-controller.php
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/testcase-rest-post-type-controller.php "$WP_TESTS_DIR"/includes/testcase-rest-post-type-controller.php
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/testcase-xmlrpc.php "$WP_TESTS_DIR"/includes/testcase-xmlrpc.php
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/testcase-ajax.php "$WP_TESTS_DIR"/includes/testcase-ajax.php
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/testcase-canonical.php "$WP_TESTS_DIR"/includes/testcase-canonical.php
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/exceptions.php "$WP_TESTS_DIR"/includes/exceptions.php
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/utils.php "$WP_TESTS_DIR"/includes/utils.php
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/spy-rest-server.php "$WP_TESTS_DIR"/includes/spy-rest-server.php
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/class-basic-object.php "$WP_TESTS_DIR"/includes/class-basic-object.php
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/class-basic-subclass.php "$WP_TESTS_DIR"/includes/class-basic-subclass.php
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/class-wp-rest-test-search-handler.php "$WP_TESTS_DIR"/includes/class-wp-rest-test-search-handler.php
	fi

	if [ ! -f "$WP_TESTS_DIR"/includes/install.php ]; then
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/install.php "$WP_TESTS_DIR"/includes/install.php
	fi

	cd $WP_TESTS_DIR

	if [ ! -d data ]; then
		mkdir data
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/themedir1/default/index.php "$WP_TESTS_DIR"/data/themedir1/default/index.php
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/themedir1/default/style.css "$WP_TESTS_DIR"/data/themedir1/default/style.css
	fi
}

install_db() {

	if [ ${SKIP_DB_CREATE} = "true" ]; then
		return 0
	fi

	# Parse database host för port eller socket referenser
	local PARTS=(${DB_HOST//\:/ })
	local DB_HOSTNAME=${PARTS[0]};
	local DB_SOCK_OR_PORT=${PARTS[1]};
	local EXTRA=""

	if ! [ -z $DB_HOSTNAME ] ; then
		if [ $(echo $DB_SOCK_OR_PORT | grep -e '^[0-9]\{1,\}$') ]; then
			EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
		elif ! [ -z $DB_SOCK_OR_PORT ] ; then
			EXTRA=" --socket=$DB_SOCK_OR_PORT"
		elif ! [ -z $DB_HOSTNAME ] ; then
			EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
		fi
	fi

	# Skapa databas
	mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA
}

install_wp
install_test_suite
install_db 