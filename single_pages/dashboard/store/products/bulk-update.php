<?php

use Concrete\Package\CommunityStoreBulkProductUpdating\Subject;

/**
 * @var Concrete\Package\CommunityStoreBulkProductUpdating\Controller\SinglePage\Dashboard\Store\Products\BulkUpdate $controller
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Page\View\PageView $view
 * @var Concrete\Package\CommunityStoreBulkProductUpdating\Subject $subjects
 * @var Concrete\Package\CommunityStoreBulkProductUpdating\UI $ui
 * @var int $pageSize
 */

defined('C5_EXECUTE') or die('Access Denied.');

$serializedSubjects = array_map(
    static function (Subject $subject) {
        return [
            'handle' => $subject->getHandle(),
            'name' => $subject->getName(),
        ];
    },
    $subjects
);
?>
<div class="ccm-dashboard-header-buttons">
    <div id="cs-bpu-header" v-cloak>
        <div class="<?= $ui->majorVersion >= 9 ? 'input-group' : 'form-inline' ?>">
            <select class="form-control" v-model="subject" v-if="SUBJECTS.length !== 1">
                <option v-for="subject in SUBJECTS" v-bind:key="subject.handle" v-bind:value="subject">{{ subject.name }}</option>
            </select>
            <input type="search" ref="searchText" class="form-control" autocomplete="off" placeholder="<?= t('Search Product') ?>" v-on:keyup.enter.prevent="search" v-bind:readonly="disabled" v-model="searchText">
            <button class="btn btn-info" v-bind:disabled="disabled" v-on:click.prevent="search"><i class="<?= $ui->faSearch ?>"></i></button>
        </div>
        <div class="<?= $ui->majorVersion >= 9 ? 'input-group input-group-sm' : 'form-inline' ?>">
            <select class="form-control" v-model="enabled">
                <option v-bind:value="true"><?= t('Show only enabled products') ?></option>
                <option v-bind:value="false"><?= t('Show only disabled products') ?></option>
                <option v-bind:value="null"><?= t('Show all products') ?></option>
            </select>
        </div>
    </div>
</div>
<div id="cs-bpu-body" v-cloak>
    <div v-if="records.length === 0">
        <div v-if="!busy" class="alert alert-info">
            <?= t('No products satisfy the search criteria.') ?>
        </div>
    </div>
    <div v-else>
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th><?= t('Product') ?></th>
                    <th><?= t('SKU') ?></th>
                    <th>{{ subject.name }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="record in recordsInPage" v-bind:key="record.key">
                    <td>
                        {{ record.name }}
                        <span v-if="record.labels">
                            <span class="<?= $ui->primaryBadge ?>" v-for="label in record.labels" v-bind:key="label">{{ label }}</span>
                        </span>
                    </td>
                    <td>{{ record.sku }}</td>
                    <td>
                        <div v-if="false">{{ record }}</div>
                        <?php
                        foreach ($subjects as $subject) {
                            ?>
                            <div v-else-if="<?= h('subject.handle === ' . json_encode($subject->getHandle())) ?>">
                                <?= $subject->insertVueComponentElement() ?>
                            </div>
                            <?php
                        }
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <div v-if="showPagination" class="ccm-pagination-wrapper">
            <ul class="pagination">
                <li class="page-item prev" v-bind:class="{disabled: busy || !hasPrevPage}">
                    <a v-if="hasPrevPage" class="page-link" href="#" v-on:click.prevent="gotoPage(pageIndex - 1)">&larr; <?= t('Previous') ?></a>
                    <span v-else class="page-link">&larr; <?= t('Previous') ?></span>
                </li>
                <li class="page-item active">
                    <span class="page-link">{{ pageIndex + 1 }}</span>
                </li>
                <li class="page-item next" v-bind:class="{disabled: busy || !hasNextPage}">
                    <a v-if="hasNextPage" class="page-link" href="#" v-on:click.prevent="gotoPage(pageIndex + 1)"><?= t('Next') ?> &rarr;</a>
                    <span v-else class="page-link"><?= t('Next') ?> &rarr;</span>
                </li>
            </ul>
        </div>
    </div>
</div>
<?php
foreach ($subjects as $subject) {
    echo $subject->createVueComponentHtml($ui);
}
?>

<script>
$(document).ready(function() {

<?php
foreach ($subjects as $subject) {
    echo $subject->createVueComponentJavascript();
}
?>

// Sadly, in ConcreteCMS 9+ the element .ccm-dashboard-header-buttons is
// moved to another DOM tree, so we need two Vue instances
const bridge = {
    header: null,
    main: null,
    ready: false,
    checkReady: function() {
        if (this.ready || !this.header || !this.main) {
            return;
        }
        this.header.disabled = false;
        this.ready = true;
        this.header.$nextTick(() => {
            this.header.$refs.searchText.focus();
            this.header.search();
        });
    },
};

const LSKEY_SUBJECT = 'comunity_store_bulk_product_subject';
const LSKEY_SEARCHTEXT = 'comunity_store_bulk_product_searchText';
const LSKEY_ENABLED = 'comunity_store_bulk_product_enabled';

new Vue({
    el: '#cs-bpu-header',
    data() {
        const SUBJECTS = <?= json_encode($serializedSubjects) ?>;
        let subject = SUBJECTS[0];
        let searchText = '';
        let enabled = true;
        if (window.localStorage) {
            if (SUBJECTS.length > 1) {
                let storedSubjectHandle = window.localStorage.getItem(LSKEY_SUBJECT);
                if (storedSubjectHandle) {
                    SUBJECTS.some((s) => {
                        if (storedSubjectHandle === s.handle) {
                            subject = s;
                            return true;
                        }
                    });
                }
            }
            searchText = window.localStorage.getItem(LSKEY_SEARCHTEXT);
            if (typeof searchText !== 'string') {
                searchText = '';
            }
            let enabledSerialized = window.localStorage.getItem(LSKEY_ENABLED);
            if (enabledSerialized === 'x') {
                enabled = false;
            } else if(enabledSerialized === '*') {
                enabled = null;
            }
        }
        return {
            disabled: true,
            SUBJECTS,
            subject,
            searchText,
            enabled,
        };
    },
    mounted() {
        bridge.header = this;
        bridge.checkReady();
    },
    watch: {
        subject() {
            if (this.SUBJECTS.length > 1 && window.localStorage) {
                window.localStorage.setItem(LSKEY_SUBJECT, this.subject.handle);
            }
        },
        searchText() {
            if (window.localStorage) {
                if (this.searchText.replace(/\s+/g, '') === '') {
                    window.localStorage.removeItem(LSKEY_SEARCHTEXT);
                } else {
                    window.localStorage.setItem(LSKEY_SEARCHTEXT, this.searchText);
                }
            }
        },
        enabled() {
            if (window.localStorage) {
                if (this.enabled === true) {
                    window.localStorage.removeItem(LSKEY_ENABLED);
                } else {
                    window.localStorage.setItem(LSKEY_ENABLED, this.enabled === false ? 'x' : '*');
                }
            }
        },
    },
    methods: {
        search() {
            if (this.disabled) {
                return;
            }
            bridge.main.search(this.subject.handle, this.subject.name, this.searchText, this.enabled);
        },
    },
});

function extractAjaxError(xhr, error) {
    let message = xhr?.responseJSON?.error?.message;
    if (typeof message === 'string' && message !== '') {
        return message;
    }
    message = xhr?.responseJSON?.error;
    if (typeof message === 'string' && message !== '') {
        return message;
    }
    message = error?.toString()
    if (typeof message === 'string' && message !== '') {
        return message;
    }
    return <?= json_encode(t('Unknown error')) ?>;
}

new Vue({
    el: '#cs-bpu-body',
    data() {
        return {
            busy: false,
            subject: null,
            records: [],
            criteria: null,
            pageIndex: 0,
            pageSize: <?= $pageSize ?>,
        };
    },
    components: {
    },
    mounted() {
        bridge.main = this;
        bridge.checkReady();
    },
    computed: {
        showPagination() {
            if (this.records.length === 0) {
                return false;
            }
            return this.criteria.nextPageAt !== '' || this.records.length > this.pageSize;
        },
        numLoadedPages() {
            return Math.ceil(this.records.length / this.pageSize);
        },
        hasPrevPage() {
            return this.pageIndex > 0;
        },
        hasNextPage() {
            if (this.records.length === 0) {
                return false;
            }
            if (this.criteria.nextPageAt !== '') {
                return true;
            }
            return this.pageIndex + 1 < this.numLoadedPages;
        },
        recordsInPage() {
            return this.records.slice(this.pageIndex * this.pageSize, this.pageIndex * this.pageSize + this.pageSize);
        },
    },
    methods: {
        search(subjectHandle, subjectName, searchText, enabled) {
            if (this.busy) {
                return;
            }
            this.subject = {handle: subjectHandle, name: subjectName};
            this.criteria = {
                <?= json_encode($token::DEFAULT_TOKEN_NAME) ?>: <?= json_encode($token->generate('community_store_bulk_product_updating')) ?>,
                subject: this.subject.handle,
                searchText,
                enabled: enabled === null ? '*' : enabled ? 'y' : 'n',
                nextPageAt: '',
            };
            this.records.splice(0, this.records.length);
            this.pageIndex = 0;
            this.fetchPage(0);
        },
        gotoPage(pageIndex) {
            if (this.busy) {
                return;
            }
            pageIndex = Math.min(Math.max(pageIndex, 0), this.numLoadedPages + 1);
            if (pageIndex === this.pageIndex) {
                return;
            }
            if (pageIndex < this.numLoadedPages) {
                this.pageIndex = pageIndex;
                return;
            }
            this.fetchPage(pageIndex);
        },
        fetchPage(pageIndex) {
            bridge.header.disabled = true;
            this.busy = true;
            $.ajax({
                url: <?= json_encode((string) $view->action('search')) ?>,
                method: 'POST',
                data: this.criteria,
                dataType: 'json',
            })
            .done((data) => {
                if (!Array.isArray(data?.records)) {
                    ConcreteAlert.error({
                        message: <?= json_encode(t('Wrong data returned from server')) ?>,
                        plainTextMessage: true,
                    });
                    return;
                }
                data.records.forEach((record) => {
                    record.busy = false;
                    this.records.push(record);
                });
                this.criteria.nextPageAt = data.nextPageAt ?? '';
                this.pageIndex = pageIndex;
            })
            .fail((xhr, status, error) => {
                ConcreteAlert.error({
                    message: extractAjaxError(xhr, error),
                    plainTextMessage: true,
                });
            })
            .always(() => {
                bridge.header.disabled = false;
                this.busy = false;
            });
        },
        updateRecord(record, newData) {
            if (this.busy || record.busy) {
                return;
            }
            record.busy = true;
            $.ajax({
                url: <?= json_encode((string) $view->action('saveItem')) ?>,
                method: 'POST',
                data: {
                    <?= json_encode($token::DEFAULT_TOKEN_NAME) ?>: <?= json_encode($token->generate('community_store_bulk_product_updating')) ?>,
                    subject: this.subject.handle,
                    itemKey: record.key,
                    oldData: record.subjectData,
                    newData,
                },
                dataType: 'json',
            })
            .done((data) => {
                if (!data?.subjectData) {
                    ConcreteAlert.error({
                        message: <?= json_encode(t('Wrong data returned from server')) ?>,
                        plainTextMessage: true,
                    });
                    return;
                }
                for (const key in data.subjectData) {
                    record.subjectData[key] = data.subjectData[key];
                }
            })
            .fail((xhr, status, error) => {
                ConcreteAlert.error({
                    message: extractAjaxError(xhr, error),
                    plainTextMessage: true,
                });
            })
            .always(() => {
                record.busy = false;
            });
        },
    },
});

});
</script>
