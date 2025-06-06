import {PrivacyNoteElementRepository} from "../../repository/privacy-note-element-repository";

export default class PrivacyNotesService {


    constructor() {
        this._privacyNote = new PrivacyNoteElementRepository();
    }

    initCheckbox() {
        this._privacyNote.observeButtons();
    }

    validateForButton(expressButton) {

        const privacyNoteElement = this._privacyNote.findClosest(expressButton);

        if (!privacyNoteElement instanceof HTMLDivElement) {
            return;
        }

        return this._privacyNote.validate(privacyNoteElement);
    }

}