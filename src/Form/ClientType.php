<?php

namespace App\Form;

use App\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Naam van de klant',
                'empty_data' => '',
                'help' => 'Alleen zichtbaar in de admin, nooit in de URL.',
            ])
            ->add('logo', FileType::class, [
                'label' => 'Logo',
                'mapped' => false,
                'required' => false,
                'help' => 'PNG, JPG of WebP, max. 1 MB. Laat leeg om het huidige logo te houden.',
                'constraints' => [
                    new File(
                        maxSize: '1M',
                        mimeTypes: ['image/png', 'image/jpeg', 'image/webp'],
                        mimeTypesMessage: 'Kies een PNG, JPG of WebP. SVG laten we niet toe, want daar kan code in zitten.',
                    ),
                ],
            ])
            ->add('primaryColor', ColorType::class, ['label' => 'Hoofdkleur', 'help' => 'Jas van de skiër en de knoppen.'])
            ->add('secondaryColor', ColorType::class, ['label' => 'Tweede kleur', 'help' => 'Vlaggen van de poortjes.'])
            ->add('accentColor', ColorType::class, ['label' => 'Accentkleur', 'help' => 'Muts en details.'])
            ->add('message', TextareaType::class, [
                'label' => 'Kerstwens',
                'empty_data' => '',
                'attr' => ['maxlength' => Client::MESSAGE_MAX, 'rows' => 3],
                'help' => \sprintf('Max. %d tekens.', Client::MESSAGE_MAX),
            ])
            ->add('active', CheckboxType::class, ['label' => 'Online', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Client::class]);
    }
}
