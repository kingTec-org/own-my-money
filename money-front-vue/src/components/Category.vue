<template>
  <section class="hero">
    <div class="hero-head">
      <breadcrumb :items.sync="this.breadcrumbItems"></breadcrumb>
    </div>
    <div class="hero-body">
      <div class="container box">
        <h1 class="title container">{{ $tc('objects.category', 1)}}</h1>
        <p class="subtitle has-text-grey">{{ $t('actions.editCategory') }}</p>
        <form @submit.prevent="validateBeforeSubmit" novalidate class="section is-max-width-form">

          <div class="field is-horizontal">
            <div class="field-label is-normal">
              <label class="label">{{ $t('fieldnames.label') }}</label>
            </div>
            <div class="field-body">
              <div class="field">
                <div class="control has-icons-right">
                  <input class="input" type="text" name="label" placeholder="Type a short description" v-model="category.label" v-validate="'required|min:3'" :class="{ 'is-danger': errors.has('label') }">
                  <span class="icon is-small is-right" v-show="errors.has('label')">
                    <i class="fa fa-exclamation-triangle"></i>
                  </span>
                  <span v-show="errors.has('label')" class="help is-danger">{{ errors.first('label') }}</span>
                </div>
              </div>
            </div>
          </div>

          <div class="field is-horizontal" v-if="!isCategory">
            <div class="field-label is-normal">
              <label class="label">{{ $t('fieldnames.parent') }}</label>
            </div>
            <div class="field-body">
              <div class="field">
                <div class="control">
                  <div class="select">
                    <select name="parent" v-model="category.parentId" v-validate="'required'" :class="{ 'is-danger': errors.has('label') }">
                      <option v-for="parentCategory in parentCategories" :key="parentCategory.id" v-bind:value="parentCategory.id">{{ parentCategory.label }}</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="field is-horizontal" v-if="isCategory">
            <div class="field-label is-normal">
              <label class="label">{{ $t('fieldnames.icon') }}</label>
            </div>
            <div class="field-body">
              <div class="field">
                <div class="control has-icons-right">
                  <div class="button" v-on:click="toggleIconPicker" v-show="!isIconPickerDisplayed"><i class="fa fa-fw" :class="category.icon"></i></div>
                  <icon-picker v-show="isIconPickerDisplayed" v-on:selectIcon="setIcon"></icon-picker>
                  <span v-show="!category.icon" class="help is-danger">Icon is required.</span>
                </div>
              </div>
            </div>
          </div>

          <div class="field is-horizontal" v-if="isCategory">
            <div class="field-label">
              <label class="label">{{ $t('fieldnames.isBudgeted') }}</label>
            </div>
            <div class="field-body">
              <div class="field">
                <div class="control">
                  <b-switch v-model="category.isBudgeted">{{ category.isBudgeted ? $t('labels.isBudgeted') : $t('labels.isNotBudgeted') }}</b-switch>
                </div>
                <p class="help">{{ $t('labels.isBudgetedHelp') }}</p>
              </div>
            </div>
          </div>

          <div class="field is-horizontal">
            <div class="field-label">
              <label class="label">{{ $t('fieldnames.status') }}</label>
            </div>
            <div class="field-body">
              <div class="field">
                <div class="control">
                  <b-switch v-model="category.status">{{ category.status ? $t('labels.active') : $t('labels.disabled') }}</b-switch>
                </div>
              </div>
            </div>
          </div>

          <div class="field is-horizontal">
            <div class="field-label is-normal">
            </div>
            <div class="field-body">
              <div class="field is-grouped">
                <div class="control">
                  <button class="button is-primary" :disabled="!isOnline"><span class="fa fa-save fa-fw fa-mr" aria-hidden="true"></span>{{ $t('actions.save') }}</button>
                </div>
                <div class="control">
                  <a @click="$router.go(-1)" class="button is-light"><span class="fa fa-ban fa-fw fa-mr" aria-hidden="true"></span>{{ $t('actions.cancel') }}</a>
                </div>
              </div>
            </div>
          </div>

          <div class="field is-horizontal" v-if="error">
            <div class="field-label is-normal">
            </div>
            <div class="field-body">
              <div class="message is-danger">
                <div class="message-body">
                  {{ error }}
                </div>
              </div>
            </div>
          </div>

          <b-loading :is-full-page="false" :active.sync="isLoading"></b-loading>
        </form>
      </div>
    </div>
  </section>
</template>

<script>
import Config from './../services/Config'
import Breadcrumb from '@/components/Breadcrumb'
import { fontAwesomePicker } from 'font-awesome-picker'
export default {
  name: 'category',
  components: {
    Breadcrumb,
    'icon-picker': fontAwesomePicker
  },
  props: ['isCategory'],
  data () {
    return {
      rCategories: this.$resource(Config.API_URL + 'categories{/id}'),
      // category
      category: {
        id: parseInt(this.$route.params.id),
        status: true
      },
      parentCategories: [],
      isIconPickerDisplayed: false,
      breadcrumbItems: [],
      isLoading: false,
      error: null
    }
  },
  computed: {
    isOnline () {
      return this.$store.state.isOnline
    }
  },
  methods: {
    // get category informations
    get () {
      this.isLoading = true
      this.rCategories.get({ id: this.category.id })
        .then((response) => {
          this.category = response.body
          this.updateBreadcrumbItems()
        }, (response) => {
        // @TODO : add error handling
          console.error(response)
        })
        .finally(function () {
          // remove loading overlay when API replies
          this.isLoading = false
        })
    },
    updateBreadcrumbItems () {
      // need to copy breadcrumb items array for sync
      const breadcrumbItems = this.breadcrumbItems.slice()
      let currentLevel = 2
      if (!this.isCategory) {
        // it is a subcategory, find and display the parent category
        currentLevel = 3
        if (this.category.parentId) {
          // parent is kwnow, try to find it
          var label = this.category.parentId
          if (this.parentCategories.length > 0) {
            const parent = this.parentCategories.filter((pcategory) => pcategory.id === parseInt(this.category.parentId))
            if (parent.length > 0) {
              // parent is found, update the breadcrumb
              label = parent[0].label
              breadcrumbItems[currentLevel - 1] = { link: { name: 'category', params: { id: this.category.parentId } }, text: label }
            }
          }
        }
      }
      if (this.category.label) {
        // label is kwnow, display it in last breadcrumbs item (regardless category or subcategory)
        breadcrumbItems[currentLevel] = { link: '/categories', text: this.category.label, isActive: true }
      }
      // update breadcrumbs items
      this.breadcrumbItems = breadcrumbItems.slice()
    },
    getParentCategories () {
      // try to get categories from local storage
      if (localStorage.getItem('categories')) {
        this.parentCategories = JSON.parse(localStorage.getItem('categories'))
        this.updateBreadcrumbItems()
        return
      }
      // categories was not found in local storage, call API
      this.rCategories.query({ status: 'all' })
        .then((response) => {
          this.parentCategories = response.body
          this.updateBreadcrumbItems()
        }, (response) => {
        // @TODO : add error handling
          console.error(response)
        })
    },
    toggleIconPicker () {
      this.isIconPickerDisplayed = !this.isIconPickerDisplayed
    },
    setIcon (selectedIcon) {
      this.category.icon = selectedIcon.className
      this.toggleIconPicker()
    },
    validateBeforeSubmit () {
      this.error = null
      // call the async validator
      this.$validator.validateAll().then((result) => {
        if (result) {
          this.isLoading = true
          // if validation is ok, call category API
          if (!this.category.id) {
            // creating new (sub)category
            this.rCategories.save(this.category)
              .then((response) => {
                localStorage.removeItem('categoriesActives')
                localStorage.removeItem('categories')
                // return to categories
                this.$router.replace({ name: 'categories' })
              }, (response) => {
                if (response.body.message) {
                  this.error = response.body.message
                  return
                }
                this.error = response.status + ' - ' + response.statusText
              })
              .finally(function () {
                // remove loading overlay when API replies
                this.isLoading = false
              })
            return
          }
          // updating new (sub)category
          this.rCategories.update({ id: this.category.id }, this.category)
            .then((response) => {
              localStorage.removeItem('categoriesActives')
              localStorage.removeItem('categories')
              // return to categories
              this.$router.replace({ name: 'categories' })
            }, (response) => {
              if (response.body.message) {
                this.error = response.body.message
                return
              }
              this.error = response.status + ' - ' + response.statusText
            })
            .finally(function () {
              // remove loading overlay when API replies
              this.isLoading = false
            })
        }
      })
    }
  },
  mounted () {
    this.breadcrumbItems = [
      { link: '/', icon: 'fa-home', text: this.$t('labels.home') },
      { link: '/categories', icon: 'fa-folder-open-o', text: this.$tc('objects.category', 2) }
    ]
    if (this.category.id) {
      // for existing category, get data
      this.get()
    }
    if (!this.isCategory) {
      // for subcategory, get parent category id from path and get all ids/labels
      this.category.parentId = parseInt(this.$route.params.pid)
      this.getParentCategories()
    } else if (!this.category.icon) {
      // set default icon for new category
      this.category.icon = 'fa-question'
    }
  }
}
</script>
