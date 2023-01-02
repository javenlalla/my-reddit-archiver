// assets/controllers/search-posts-controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        endpoint: String
    }

    static targets = ['query', 'subreddit', 'flairText']

    connect() {
        this.doSearch()
    }

    execSearch() {
        this.doSearch()
    }

    doSearch() {
        let endpoint = this.endpointValue + this.queryTarget.value;

        if (this.subredditTarget.value !== '') {
            endpoint += '&subreddits=' + this.subredditTarget.value;
        }

        if (this.flairTextTarget.value !== '') {
            endpoint += '&flairTexts=' + this.flairTextTarget.value;
        }

        fetch(endpoint)
            .then(response => response.text())
            .then(html => this.element.querySelector("#search-results").innerHTML = html)
    }
}