// assets/controllers/search-posts-controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        endpoint: String
    }

    static targets = ['query']

    execSearch() {
        // console.log(this.queryTarget.value);
        this.doSearch()
    }

    doSearch() {
        const endpoint = this.endpointValue + this.queryTarget.value;

        fetch(endpoint)
            .then(response => response.text())
            .then(html => this.element.querySelector("#search-results").innerHTML = html)
    }

    connect() {
        this.doSearch()
    }
}