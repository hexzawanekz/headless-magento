define(
    [
        'ko',
        'uiComponent',
        'mage/translate',
        'jquery',
        'mage/storage',
        'uiLayout',
        'Magento_Ui/js/modal/modal'
    ],
    function (
        ko,
        Component,
        $t,
        $,
        storage,
        layout,
        modal
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'StripeIntegration_Tax/tax_classes',
                errorUninitialized: $.mage.__('There was a problem communicating with Stripe. Please check that your API keys are correct, and the Stripe PHP library is installed.'),
            },

            initObservable: function ()
            {
                this._super()
                    .observe([
                        'newTaxClassName',
                        'newTaxClassCode',
                        'taxClasses',
                        'productTaxCodes',
                        'actionsListOpened',
                        'formKey',
                        'savingChanges',
                        'selectedProductTaxCode',
                        'searchText',
                        'searchTaxClassesText',
                        'editingRow',
                        'paginationLimit',
                        'paginationMenuOpened',
                        'paginationPage',
                        'paginationTotalPages',
                        'sortByName',
                        'sortByCategory',
                        'sortByCode'
                    ]);

                this.filteredTaxClasses = ko.computed(this.filterTaxClasses, this);
                this.sortedTaxClasses = ko.computed(this.sortTaxClasses, this);
                this.paginatedTaxClasses = ko.computed(this.paginateTaxClasses, this);
                this.paginationLimit(parseInt(localStorage.getItem('tax_classes_pagination_limit')) || 10);
                this.paginationPage(1);
                this.paginationOptions = [10, 25, 50, 100];
                this.sortByCategory(null);
                this.sortByCode(null);
                this.sortByName('asc');

                this.paginationTotalPages = ko.computed(function ()
                {
                    if (this.paginationLimit() == 0 || isNaN(this.paginationLimit()) || this.paginationLimit() < 0)
                    {
                        return 1;
                    }

                    return Math.ceil(this.filteredTaxClasses().length / this.paginationLimit());
                }, this);

                this.paginationLimit.subscribe(function (newLimit)
                {
                    if (this.paginationPage() > this.paginationTotalPages())
                    {
                        this.paginationPage(this.paginationTotalPages());
                    }

                    if (!isNaN(newLimit) && newLimit > 0)
                    {
                        localStorage.setItem('tax_classes_pagination_limit', newLimit);
                    }
                }, this);

                this.paginationPage.subscribe(function (newPage)
                {
                    if (newPage > this.paginationTotalPages())
                    {
                        this.paginationPage(this.paginationTotalPages());
                    }
                }, this);

                var self = this;

                // When the user left clicks anywhere outside .action-menu and not on .action-select, close it
                $(document).on('mousedown tap', function (e)
                {
                    var isLeftClickOrTap = e.button === 0 || e.type === 'tap';
                    var isInsideActionMenu = $(e.target).closest('.action-menu').length > 0;
                    var isInsideEditableRow = $(e.target).closest('.data-grid-editable-row').length > 0;
                    var isActionSelect = $(e.target).hasClass('action-select');
                    var isRenameAction = $(e.target).hasClass('action-rename') || $(e.target).parent().hasClass('action-rename');

                    if (isLeftClickOrTap && !isInsideActionMenu && !isActionSelect)
                    {
                        self.closeActionsMenu();
                    }

                    if (isLeftClickOrTap && !isInsideEditableRow && !isActionSelect && !isRenameAction && self.editingRow() !== null)
                    {
                        self.cancelTaxClassRowEditing(self.editingRow());
                    }
                });

                // When the user presses the escape button, close the action menu
                $(document).keyup(function (e)
                {
                    if (e.key === 'Escape')
                    {
                        self.closeActionsMenu();

                        if (self.editingRow() !== null) {
                            self.cancelTaxClassRowEditing(self.editingRow());
                        }
                    }
                });

                // When the enter key is pressed, save the editable row
                $(document).keypress(function (e)
                {
                    if (e.key === 'Enter' && self.editingRow() !== null)
                    {
                        self.saveTaxClassRow(self.editingRow());
                        e.preventDefault();
                    }
                });

                // Listen to the saveChanges event
                document.addEventListener('saveChanges', this.saveChanges.bind(this));

                this.filteredProductTaxCodes = ko.computed(function() {
                    var search = self.searchText();
                    if (!search || !search.length)
                    {
                        return self.productTaxCodes();
                    }

                    search = search.toLowerCase();

                    return ko.utils.arrayFilter(self.productTaxCodes(), function(obj) {
                      return obj.name.toLowerCase().indexOf(search) >= 0;
                    });
                });

                return this;
            },

            filterTaxClasses: function()
            {
                var results = this.taxClasses();
                var search = this.searchTaxClassesText();

                if (search && search.length)
                {
                    search = search.toLowerCase();

                    results = ko.utils.arrayFilter(results, function(obj)
                    {
                        if (obj.class_name && obj.class_name.toLowerCase().indexOf(search) >= 0)
                            return true;

                        if (obj.stripe_product_tax_code_name && obj.stripe_product_tax_code_name.toLowerCase().indexOf(search) >= 0)
                            return true;

                        if (obj.stripe_product_tax_code && obj.stripe_product_tax_code.toLowerCase().indexOf(search) >= 0)
                            return true;

                        return false;
                    });
                }

                return results;
            },

            sortTaxClasses: function()
            {
                var results = this.filteredTaxClasses();

                if (this.sortByName() === 'asc')
                {
                    results = results.sort(function(a, b)
                    {
                        if (!a || !a.class_name)
                        {
                            return -1;
                        }

                        if (!b || !b.class_name)
                        {
                            return 1;
                        }

                        return a.class_name.localeCompare(b.class_name);
                    });
                }
                else if (this.sortByName() === 'desc')
                {
                    results = results.sort(function(a, b)
                    {
                        if (!a || !a.class_name)
                        {
                            return 1;
                        }

                        if (!b || !b.class_name)
                        {
                            return -1;
                        }

                        return b.class_name.localeCompare(a.class_name);
                    });
                }

                if (this.sortByCategory() === 'asc')
                {
                    results = results.sort(function(a, b)
                    {
                        if (!a || !a.stripe_product_tax_code_name)
                        {
                            return -1;
                        }

                        if (!b || !b.stripe_product_tax_code_name)
                        {
                            return 1;
                        }

                        return a.stripe_product_tax_code_name.localeCompare(b.stripe_product_tax_code_name);
                    });
                }
                else if (this.sortByCategory() === 'desc')
                {
                    results = results.sort(function(a, b)
                    {
                        if (!a || !a.stripe_product_tax_code_name)
                        {
                            return 1;
                        }

                        if (!b || !b.stripe_product_tax_code_name)
                        {
                            return -1;
                        }

                        return b.stripe_product_tax_code_name.localeCompare(a.stripe_product_tax_code_name);
                    });
                }

                if (this.sortByCode() === 'asc')
                {
                    results = results.sort(function(a, b)
                    {
                        if (!a || !a.stripe_product_tax_code)
                        {
                            return -1;
                        }

                        if (!b || !b.stripe_product_tax_code)
                        {
                            return 1;
                        }

                        return a.stripe_product_tax_code.localeCompare(b.stripe_product_tax_code);
                    });
                }
                else if (this.sortByCode() === 'desc')
                {
                    results = results.sort(function(a, b)
                    {
                        if (!a || !a.stripe_product_tax_code)
                        {
                            return 1;
                        }

                        if (!b || !b.stripe_product_tax_code)
                        {
                            return -1;
                        }

                        return b.stripe_product_tax_code.localeCompare(a.stripe_product_tax_code);
                    });
                }

                return results;
            },

            paginateTaxClasses: function ()
            {
                this.closePagination();
                var results = this.sortedTaxClasses();
                var limit = 10;

                // Further filter the results by pagination
                try
                {
                    limit = parseInt(this.paginationLimit());
                }
                catch (e)
                {
                    console.warn("Error parsing pagination limit: " + e);
                }

                if (this.paginationPage() > this.paginationTotalPages())
                {
                    this.paginationPage(this.paginationTotalPages());
                }
                else if (this.paginationPage() < 1)
                {
                    this.paginationPage(1);
                }

                var page = this.paginationPage();
                if (!isNaN(page) && page > 0 && !isNaN(limit) && limit > 0)
                {
                    var start = Math.max(0, (page - 1) * limit);
                    var end = Math.min(start + limit, results.length);

                    results = results.slice(start, end);
                }

                return results;
            },

            openModalAddNewTaxClass: function ()
            {
                this.validateStripeAPI();
                this.searchText('');
                this.selectedProductTaxCode(null);

                var self = this;

                var options = {
                    type: 'popup',
                    responsive: true,
                    innerScroll: true,
                    title: 'Add a new tax class',
                    buttons: [
                        {
                            text: $.mage.__('Add'),
                            class: 'action-primary add-tax-class-button',
                            click: () => {
                                this.addNewTaxClass();
                                // Declaring the function this way, causes `this` to be bound to the UIComponent,
                                // not the modal instance
                                $('#tax-class-modal').modal('closeModal');
                            }
                        },
                        {
                            text: $.mage.__('Cancel'),
                            class: 'action-secondary action-dismiss',
                            click: function () {
                                this.closeModal();
                            }
                        }
                    ]
                };

                // Create the modal using the options defined above
                var popup = modal(options, $('#tax-class-modal'));
                $('#tax-class-modal').modal('openModal');

                var primaryButton = $('.modal-footer button.action-primary');
                primaryButton.attr('disabled', 'disabled');
            },

            openModalChangeCategory: function (selectedTaxClass)
            {
                this.validateStripeAPI();
                this.searchText('');
                this.selectedProductTaxCode(null);

                var self = this;

                var options = {
                    type: 'popup',
                    responsive: true,
                    innerScroll: true,
                    title: 'Set tax class category',
                    buttons: [
                        {
                            text: $.mage.__('Set'),
                            class: 'action-primary set-tax-class-button',
                            click: () => {
                                this.changeTaxClassCategory(selectedTaxClass);
                                // Declaring the function this way, causes `this` to be bound to the UIComponent,
                                // not the modal instance
                                $('#tax-class-modal').modal('closeModal');
                            }
                        },
                        {
                            text: $.mage.__('Cancel'),
                            class: 'action-secondary action-dismiss',
                            click: function () {
                                this.closeModal();
                            }
                        }
                    ]
                };

                // Create the modal using the options defined above
                var popup = modal(options, $('#tax-class-modal'));
                $('#tax-class-modal').modal('openModal');

                var primaryButton = $('.modal-footer button.action-primary');
                primaryButton.attr('disabled', 'disabled');
                this.closeActionsMenu();
            },

            addNewTaxClass: function ()
            {
                var selection = this.selectedProductTaxCode();
                if (!selection || !selection.id)
                {
                    return;
                }

                var taxClasses = this.taxClasses();
                taxClasses.push({
                    class_name: selection.name,
                    stripe_product_tax_code_name: selection.name,
                    stripe_product_tax_code: selection.id
                });

                this.taxClasses(taxClasses);
            },

            changeTaxClassCategory: function (selectedTaxClass)
            {
                var selection = this.selectedProductTaxCode();
                if (!selection || !selection.id) {
                    return;
                }

                var taxClasses = this.taxClasses();

                var index = taxClasses.findIndex(function (obj) {
                    return obj === selectedTaxClass;
                });

                if (index === -1) {
                    return;
                }

                // Create a shallow copy of the object to modify
                var taxClass = { ...taxClasses[index] };

                // Modify the copy
                taxClass.stripe_product_tax_code = selection.id;
                taxClass.stripe_product_tax_code_name = selection.name;
                if (!taxClass.class_name || taxClass.class_name.length === 0) {
                    taxClass.class_name = selection.name;
                }

                // Replace the object in the array
                taxClasses.splice(index, 1, taxClass);
                this.taxClasses(taxClasses);
            },

            unsetTaxClassCategory: function (taxClass)
            {
                var taxClasses = this.taxClasses();

                var index = taxClasses.findIndex(function (obj) {
                    return obj === taxClass;
                });

                if (index === -1) {
                    return;
                }

                // Create a shallow copy of the object to modify
                var taxClass = { ...taxClasses[index] };

                // Modify the copy
                taxClass.stripe_product_tax_code = null;
                taxClass.stripe_product_tax_code_name = null;

                // Replace the object in the array
                taxClasses.splice(index, 1, taxClass);
                this.taxClasses(taxClasses);
                this.closeActionsMenu();
            },

            renameTaxClass: function (taxClass)
            {
                this.editingRow(taxClass);
                this.closeActionsMenu();
                $('.class-name-input').focus();
                this.originalTaxClassName = taxClass.class_name;
            },

            deleteTaxClass: function (taxClass)
            {
                var taxClasses = this.taxClasses();
                taxClasses = taxClasses.filter(function (obj)
                {
                    return obj !== taxClass;
                });

                this.taxClasses(taxClasses);
                this.closeActionsMenu();
            },

            toggleActionsMenu: function (index)
            {
                if (this.actionsListOpened() === index)
                {
                    this.actionsListOpened(null);
                }
                else
                {
                    this.actionsListOpened(index);
                }
            },

            closeActionsMenu: function()
            {
                this.actionsListOpened(null);
            },

            saveChanges: function ()
            {
                this.savingChanges(true);

                // Add the form data to the form
                var form = $('#tax-classes-form');
                form.find('input[name="tax_classes"]').val(JSON.stringify(this.taxClasses()));

                // Submit the form
                $('#tax-classes-form').submit();
            },

            validateStripeAPI: function ()
            {
                if (this.productTaxCodes().length > 0)
                {
                    return true;
                }

                var options = {
                    type: 'popup',
                    responsive: true,
                    innerScroll: true,
                    title: 'Cannot perform action',
                    buttons: [
                    {
                        text: $.mage.__('Close'),
                        class: 'action-secondary action-dismiss',
                        click: function () {
                            this.closeModal();
                        }
                    }]
                };

                // Create the modal using the options defined above
                var popup = modal(options, $('#stripe-api-error-modal'));
                $('#stripe-api-error-modal').modal('openModal');

                throw new Error(this.errorUninitialized);
            },

            selectProductTaxCode: function (object)
            {
                var primaryButton = $('.modal-footer button.action-primary');
                if (this.selectedProductTaxCode() === object)
                {
                    this.selectedProductTaxCode(null);
                    primaryButton.attr('disabled', 'disabled');
                }
                else
                {
                    this.selectedProductTaxCode(object);
                    primaryButton.removeAttr('disabled');
                }
            },

            selectedProductTaxCodeId: function()
            {
                if (!this.selectedProductTaxCode())
                {
                    return null;
                }

                return this.selectedProductTaxCode().id;
            },

            saveTaxClassRow: function (taxClass)
            {
                if (!taxClass.class_name || taxClass.class_name.trim().length === 0)
                {
                    return this.cancelTaxClassRowEditing(taxClass);
                }

                var copy = { ...taxClass };
                var taxClasses = this.taxClasses();

                var index = taxClasses.findIndex(function (obj) {
                    return obj === taxClass;
                });

                if (index !== -1) {
                    taxClasses.splice(index, 1, copy);
                    this.taxClasses(taxClasses);
                }

                this.editingRow(null);
            },

            cancelTaxClassRowEditing: function (taxClass)
            {
                if (!taxClass)
                {
                    return;
                }

                taxClass.class_name = this.originalTaxClassName;
                this.editingRow(null);
            },

            openPagination: function ()
            {
                this.paginationMenuOpened(true);
            },

            closePagination: function (test)
            {
                this.paginationMenuOpened(false);
            },

            setPaginationLimit: function (limit)
            {
                this.paginationLimit(limit);
                this.closePagination();
            },

            togglePagination: function ()
            {
                this.paginationMenuOpened(!this.paginationMenuOpened());
            },

            nextPage: function ()
            {
                if (this.paginationPage() < this.paginationTotalPages())
                {
                    this.paginationPage(this.paginationPage() + 1);
                }
            },

            previousPage: function ()
            {
                if (this.paginationPage() > 1)
                {
                    this.paginationPage(this.paginationPage() - 1);
                }
            },

            isFirstPage: function ()
            {
                return this.paginationPage() === 1;
            },

            isLastPage: function ()
            {
                return this.paginationPage() === this.paginationTotalPages();
            },

            toggleSortByName: function()
            {
                this.sortByCategory(null);
                this.sortByCode(null);

                if (this.sortByName() !== 'asc')
                {
                    this.sortByName('asc');
                }
                else
                {
                    this.sortByName('desc');
                }
            },

            toggleSortByCategory: function()
            {
                this.sortByName(null);
                this.sortByCode(null);

                if (this.sortByCategory() !== 'asc')
                {
                    this.sortByCategory('asc');
                }
                else
                {
                    this.sortByCategory('desc');
                }
            },

            toggleSortByCode: function()
            {
                this.sortByName(null);
                this.sortByCategory(null);

                if (this.sortByCode() !== 'asc')
                {
                    this.sortByCode('asc');
                }
                else
                {
                    this.sortByCode('desc');
                }
            },

        });
    }
);
