#!/bin/bash

set -e

export PIMCORE_PROJECT_ROOT="${PWD}"

CMD="vendor/bin/codecept run -c . -vvv"

# Skip Installer tests by default. If PIMCORE_TEST_INSTALLER == 1, test only Installer.
if [[ -z "$PIMCORE_TEST_INSTALLER" ]] || [[ "$PIMCORE_TEST_INSTALLER" -ne 1 ]]; then
    CMD="$CMD --skip Installer"
else
    PIMCORE_TEST_SUITE="Installer"
fi

# add suite if configured
if [[ -n "$PIMCORE_TEST_SUITE" ]]; then
    CMD="$CMD $PIMCORE_TEST_SUITE"
fi

# add test group if configured
if [[ -n "$PIMCORE_TEST_GROUP" ]]; then
    CMD="$CMD -g $PIMCORE_TEST_GROUP"
fi

# add env if configured
if [[ -n "$PIMCORE_TEST_ENV" ]]; then
    CMD="$CMD --env $PIMCORE_TEST_ENV"
fi

# skip file tests unless configured otherwise
if [[ -z "$PIMCORE_TEST_CACHE_FILE" ]] || [[ "$PIMCORE_TEST_CACHE_FILE" -ne 1 ]]; then
    CMD="$CMD --skip-group cache.core.file"
fi

# generate json result file
CMD="$CMD --json"

echo $CMD
eval $CMD
