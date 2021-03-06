<template>
  <form @submit.prevent="validateBeforeSubmit" novalidate>
    <div class="modal-card">
      <header class="modal-card-head">
        <p class="modal-card-title">{{ action }} {{ $tc('objects.user', 1).toLowerCase() }}</p>
      </header>
      <section class="modal-card-body">
        <div class="field">
          <label class="label">{{ $t('fieldnames.login') }}</label>
          <div class="control has-icons-left has-icons-right">
            <input class="input" type="text" name="login" placeholder="Login" v-model="user.login" v-validate="'required|alpha_num'" :class="{ 'is-danger': errors.has('login') }">
            <span class="icon is-small is-left"><i class="fa fa-user"></i></span>
            <span class="icon is-small is-right" v-show="errors.has('login')"><i class="fa fa-exclamation-triangle"></i></span>
            <span v-show="errors.has('login')" class="help is-danger">{{ errors.first('login') }}</span>
          </div>
        </div>
        <div class="field" v-if="!user.sub">
          <label class="label">{{ $t('fieldnames.password') }}</label>
          <div class="control has-icons-left">
            <input class="input" type="password" name="password" placeholder="Password" v-model="user.password" v-validate="'required|alpha_num'" :class="{ 'is-danger': errors.has('password') }">
            <span class="icon is-small is-left"><i class="fa fa-lock"></i></span>
            <span v-show="errors.has('password')" class="help is-danger">{{ errors.first('password') }}</span>
          </div>
        </div>
        <div class="field">
          <label class="label">{{ $t('fieldnames.email') }}</label>
          <div class="control has-icons-left has-icons-right">
            <input class="input" type="email" name="email" placeholder="Mail address" v-model="user.mail" v-validate="'required|email'" :class="{ 'is-danger': errors.has('email') }">
            <span class="icon is-small is-left"><i class="fa fa-envelope"></i></span>
            <span class="icon is-small is-right" v-show="errors.has('email')"><i class="fa fa-exclamation-triangle"></i></span>
            <span v-show="errors.has('email')" class="help is-danger">{{ errors.first('email') }}</span>
          </div>
        </div>
        <div class="field">
          <label class="label">{{ $t('fieldnames.status') }}</label>
          <div class="control">
            <b-switch v-model="user.status"></b-switch>
          </div>
        </div>
        <div class="field">
          <label class="label">{{ $t('fieldnames.admin') }}</label>
          <div class="control">
            <b-switch v-model="isAdmin"></b-switch>
          </div>
        </div>
        <div class="message is-danger block" v-if="error">
          <div class="message-body">
            {{ error }}
          </div>
        </div>
        <user-connections v-if="this.user.sub" v-bind:id="this.user.sub"></user-connections>
      </section>
      <footer class="modal-card-foot">
        <button class="button is-primary" :disabled="!isOnline">{{ action }}</button>
        <button class="button" type="button" @click="$parent.close()">{{ $t('actions.cancel') }}</button>
      </footer>
      <b-loading :is-full-page="false" :active.sync="isLoading"></b-loading>
    </div>
  </form>
</template>

<script>
import Config from './../services/Config'
import UserConnections from '@/components/UserConnections'
export default {
  props: ['user'],
  components: {
    UserConnections
  },
  data () {
    return {
      error: '',
      isLoading: false,
      action: '',
      // resources
      rUsers: this.$resource(Config.API_URL + 'users{/id}')
    }
  },
  computed: {
    isOnline () {
      return this.$store.state.isOnline
    },
    isAdmin: {
      get () {
        return this.user.scope.includes('admin')
      },
      set: function (isAdmin) {
        const scope = this.user.scope.split(' ').filter((value, index, arr) => value !== 'admin')
        if (isAdmin) {
          scope.push('admin')
        }
        this.user.scope = scope.join(' ')
      }
    }
  },
  methods: {
    validateBeforeSubmit () {
      this.error = null
      // call the async validator
      this.$validator.validateAll().then((result) => {
        if (result) {
          this.isLoading = true
          // if validation is ok, call users API
          if (!this.user.sub) {
            // creating new user
            this.rUsers.save(this.user)
              .then((response) => {
                this.$parent.close()
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
          // updating user
          this.rUsers.update({ id: this.user.sub }, this.user)
            .then((response) => {
              this.$parent.close()
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
    this.action = this.user.sub ? this.$t('actions.update') : this.$t('actions.create')
  }
}
</script>
