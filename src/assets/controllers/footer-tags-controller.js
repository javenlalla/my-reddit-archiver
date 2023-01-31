import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

export default class extends Controller {
    static targets = ["displayMode", "editMode"];

    async initialize() {
        this.component = await getComponent(this.element);
    }

    edit() {
        this.displayModeTargets.forEach((target) => {
            target.classList.add("d-none");
        });
        this.editModeTarget.classList.remove("d-none");
    }

    close() {
        this.displayModeTargets.forEach((target) => {
            target.classList.remove("d-none");
        });
        this.editModeTarget.classList.add("d-none");
    }
}
