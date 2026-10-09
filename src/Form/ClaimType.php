<?php

namespace App\Form;

use App\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * What a client fills in on the claim page. Same fields as the admin, minus the name
 * (that comes from the invitation) and the online switch.
 */
class ClaimType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('logo', FileType::class, [
                'label' => 'Jullie logo',
                'mapped' => false,
                'help' => 'PNG, JPG of WebP, max. 1 MB. Liefst met een transparante of witte achtergrond.',
                'attr' => ['accept' => 'image/png,image/jpeg,image/webp'],
                'constraints' => [
                    new NotNull(message: 'Kies een logo.'),
                    new File(
                        maxSize: '1M',
                        mimeTypes: ['image/png', 'image/jpeg', 'image/webp'],
                        mimeTypesMessage: 'Kies een PNG, JPG of WebP.',
                        maxSizeMessage: 'Het logo mag max. 1 MB zijn.',
                    ),
                ],
            ])
            ->add('primaryColor', ColorType::class, ['label' => 'Hoofdkleur', 'help' => 'Jas van de skiër en de knoppen.'])
            ->add('secondaryColor', ColorType::class, ['label' => 'Tweede kleur', 'help' => 'Vlaggen van de poortjes.'])
            ->add('accentColor', ColorType::class, ['label' => 'Accentkleur', 'help' => 'Muts en details.'])
            ->add('message', TextareaType::class, [
                'label' => 'Jullie kerstwens',
                'empty_data' => '',
                'attr' => ['maxlength' => Client::MESSAGE_MAX, 'rows' => 3, 'placeholder' => 'Fijne feesten en een sportief 2027!'],
                'help' => \sprintf('Max. %d tekens.', Client::MESSAGE_MAX),
            ])
            ->add('confirm', CheckboxType::class, [
                'label' => 'Ik heb alles nagekeken. Na publiceren kan ik niets meer aanpassen.',
                'mapped' => false,
                'constraints' => [new IsTrue(message: 'Vink aan dat je alles hebt nagekeken.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Client::class]);
    }
}
