<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\Tag;
use App\Repository\TagRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;

class ContentTagsForm extends AbstractType
{
    public function __construct(private readonly TagRepository $tagRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('tags', EntityType::class, [
                'placeholder' => 'Select Tags',
                'class' => Tag::class,
                'choice_name' => 'name',
                'choice_label' => 'name',
                'choice_value' => 'name',
                'required' => false,
                'autocomplete' => true,
                'multiple' => true,
                'tom_select_options' => [
                    'placeholder' => 'Select Tags',
                    'create' => true,
                    'createOnBlur' => true,
                ],
            ])
            ->addEventListener(FormEvents::PRE_SUBMIT, function (PreSubmitEvent $event): void {
                $eventData = $event->getData();
                $form = $event->getForm();

                if (is_array($eventData) && isset($eventData['tags'])) {
                    foreach ($eventData['tags'] as $tagName) {
                        $existingTag = $this->tagRepository->findOneBy(['name' => $tagName]);
                        if (($existingTag instanceof Tag) === false) {
                            $newTag = new Tag();
                            $newTag->setName($tagName);

                            $this->tagRepository->add($newTag, true);
                        }
                    }
                }
            })
        ;


    }
}
