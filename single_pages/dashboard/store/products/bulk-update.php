<?php

use CommunityStore\BulkProductUpdating\Subject;

/**
 * @var Concrete\Package\CommunityStoreBulkProductUpdating\Controller\SinglePage\Dashboard\Store\Products\BulkUpdate $controller
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Page\View\PageView $view
 * @var CommunityStore\BulkProductUpdating\Subject $subjects
 * @var CommunityStore\BulkProductUpdating\UI $ui
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
    <div class="<?= $ui->majorVersion >= 9 ? 'input-group' : 'form-inline' ?>" id="cs-bpu-header" v-cloak>
        <select class="form-control" v-model="subject" v-if="SUBJECTS.length !== 1">
            <option v-for="subject in SUBJECTS" v-bind:key="subject.handle" v-bind:value="subject.handle">{{ subject.name }}</option>
        </select>
        <input type="search" ref="searchText" class="form-control" autocomplete="off" placeholder="<?= t('Search Product') ?>" v-on:keyup.enter.prevent="search" v-bind:readonly="disabled" v-model="searchText">
        <button class="btn btn-info" v-bind:disabled="disabled" v-on:click.prevent="search"><i class="<?= $ui->faSearch ?>"></i></button>
    </div>
</div>
<div id="cs-bpu-body" v-cloak>
    <div v-if="message !== ''" class="alert" v-bind:class="`alert-${messageClass}`">
        <span style="white-space: pre-wrap">{{ message }}</span>
    </div>
    <div v-else-if="records.length === 0" class="alert alert-info">
        <?= t('No products satisfy the search criteria.') ?>
    </div>
    <table v-else class="table table-striped table-hover">
        <thead>
            <tr>
                <th><?= t('Product') ?></th>
                <th><?= t('SKU') ?></th>
                <th>{{ subject.name }}</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="record in records" v-bind:key="record.key">
                <td>
                    {{ record.name }}
                    <span v-if="record.labels">
                        <span class="label label-primary" v-for="label in record.labels" v-bind:key="label">{{ label }}</span>
                    </span>
                </td>
                <td>{{ record.sku }}</td>
                <td>
                    <div v-if="false"></div>
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
        });
    },
};

const LSKEY_SUBJECT = 'comunity_store_bulk_product_subject';
const LSKEY_SEARCHTEXT = 'comunity_store_bulk_product_searchText';

new Vue({
    el: '#cs-bpu-header',
    data() {
        const SUBJECTS = <?= json_encode($serializedSubjects) ?>;
        let subject = SUBJECTS[0];
        let searchText = '';
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
        }
        return {
            disabled: true,
            SUBJECTS,
            subject,
            searchText,
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
    },
    methods: {
        search() {
            if (this.disabled) {
                return;
            }
            bridge.main.search(this.subject.handle, this.subject.name, this.searchText);
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
            message: <?= json_encode(t('Enter the search text and click the Search button')) ?>,
            messageClass: 'info',
        };
    },
    components: {
    },
    mounted() {
        bridge.main = this;
        bridge.checkReady();
    },
    methods: {
        search(subjectHandle, subjectName, searchText) {
            if (this.busy) {
                return;
            }
            this.subject = {handle: subjectHandle, name: subjectName};
            this.records.splice(0, this.records.length);
            this.message = <?= json_encode(t('Searching products...')) ?>;
            this.messageClass = 'info';
            bridge.header.disabled = true;
            this.busy = true;
            $.ajax({
                url: <?= json_encode((string) $view->action('search')) ?>,
                method: 'POST',
                data: {
                    <?= json_encode($token::DEFAULT_TOKEN_NAME) ?>: <?= json_encode($token->generate('community_store_bulk_product_updating')) ?>,
                    subject: this.subject.handle,
                    searchText,
                },
                dataType: 'json',
            })
            .done((data) => {
                if (!Array.isArray(data?.records)) {
                    this.messageClass = 'danger';
                    this.message = <?= json_encode(t('Wrong data returned from server')) ?>;
                    return;
                }
                data.records.forEach((record) => {
                    record.busy = false;
                    this.records.push(record);
                });
                this.message = '';
            })
            .fail((xhr, status, error) => {
                this.messageClass = 'danger';
                this.message = extractAjaxError(xhr, error);
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
