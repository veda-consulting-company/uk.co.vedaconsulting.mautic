# uk.co.vedaconsulting.mautic

Development co-funded by:
- [The Vitiligo Society](https://vitiligosociety.org)
- [The British Geriatrics Society](https://bgs.org.uk)

## Description

This extension integrates CiviCRM with [Mautic](https://www.mautic.org), the Open-Source Marketing Automation software.
It currently provides:

 - Push sync contacts from CiviCRM to Mautic. This will synchronize contacts in a
   group to a Mautic segment.
 - A CiviRules Trigger to handle Mautic webhooks.
 - CiviRules Conditions:
   - Mautic Webhook type
   - Mautic Contact matches a CiviCRM Contact
   - Mautic Contact has a tag
   - Mautic Contact field has a particular value
   - Event is linked to a Mautic Segment
 - CiviRules Actions:
   - Sync contact to Mautic
   - Create Contact from Mautic webhook data
   - Add contact to Segment mapped to an Event


Compatible with Mautic version 3.

Documentation: https://github.com/veda-consulting-company/uk.co.vedaconsulting.mautic/blob/master/docs/index.md
