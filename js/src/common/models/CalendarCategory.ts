import Model from 'flarum/common/Model';

/** A calendar category (`calendar-categories`), as the store keeps it. api.ts turns it into the plain CalCategory the views use. */
export default class CalendarCategory extends Model {
  name = Model.attribute<string>('name');
  slug = Model.attribute<string>('slug');
  color = Model.attribute<string>('color');
  rsvp = Model.attribute<boolean>('rsvp');
  goingLabel = Model.attribute<string | null>('goingLabel');
  interestedLabel = Model.attribute<string | null>('interestedLabel');
  position = Model.attribute<number>('position');
}
