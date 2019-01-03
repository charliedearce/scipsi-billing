<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class frmservice
    Inherits DevExpress.XtraEditors.XtraForm

    'Form overrides dispose to clean up the component list.
    <System.Diagnostics.DebuggerNonUserCode()> _
    Protected Overrides Sub Dispose(ByVal disposing As Boolean)
        If disposing AndAlso components IsNot Nothing Then
            components.Dispose()
        End If
        MyBase.Dispose(disposing)
    End Sub

    'Required by the Windows Form Designer
    Private components As System.ComponentModel.IContainer

    'NOTE: The following procedure is required by the Windows Form Designer
    'It can be modified using the Windows Form Designer.  
    'Do not modify it using the code editor.
    <System.Diagnostics.DebuggerStepThrough()> _
    Private Sub InitializeComponent()
        Me.GroupBox18 = New System.Windows.Forms.GroupBox()
        Me.GroupBox3 = New System.Windows.Forms.GroupBox()
        Me.btn2 = New DevExpress.XtraEditors.SimpleButton()
        Me.btn1 = New DevExpress.XtraEditors.SimpleButton()
        Me.txtServiceDesc = New System.Windows.Forms.TextBox()
        Me.Label28 = New System.Windows.Forms.Label()
        Me.Label27 = New System.Windows.Forms.Label()
        Me.Label26 = New System.Windows.Forms.Label()
        Me.txtServiceCode = New System.Windows.Forms.MaskedTextBox()
        Me.cbServiceType = New System.Windows.Forms.ComboBox()
        Me.GroupBox18.SuspendLayout()
        Me.GroupBox3.SuspendLayout()
        Me.SuspendLayout()
        '
        'GroupBox18
        '
        Me.GroupBox18.Controls.Add(Me.GroupBox3)
        Me.GroupBox18.Controls.Add(Me.txtServiceDesc)
        Me.GroupBox18.Controls.Add(Me.Label28)
        Me.GroupBox18.Controls.Add(Me.Label27)
        Me.GroupBox18.Controls.Add(Me.Label26)
        Me.GroupBox18.Controls.Add(Me.txtServiceCode)
        Me.GroupBox18.Controls.Add(Me.cbServiceType)
        Me.GroupBox18.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox18.Location = New System.Drawing.Point(13, 13)
        Me.GroupBox18.Margin = New System.Windows.Forms.Padding(4)
        Me.GroupBox18.Name = "GroupBox18"
        Me.GroupBox18.Padding = New System.Windows.Forms.Padding(4)
        Me.GroupBox18.Size = New System.Drawing.Size(477, 269)
        Me.GroupBox18.TabIndex = 21
        Me.GroupBox18.TabStop = False
        Me.GroupBox18.Text = "SERVICES"
        '
        'GroupBox3
        '
        Me.GroupBox3.Controls.Add(Me.btn2)
        Me.GroupBox3.Controls.Add(Me.btn1)
        Me.GroupBox3.FlatStyle = System.Windows.Forms.FlatStyle.Flat
        Me.GroupBox3.Font = New System.Drawing.Font("Segoe UI", 10.2!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.GroupBox3.Location = New System.Drawing.Point(18, 176)
        Me.GroupBox3.Margin = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox3.Name = "GroupBox3"
        Me.GroupBox3.Padding = New System.Windows.Forms.Padding(6, 7, 6, 7)
        Me.GroupBox3.Size = New System.Drawing.Size(287, 78)
        Me.GroupBox3.TabIndex = 29
        Me.GroupBox3.TabStop = False
        Me.GroupBox3.Text = "FUNCTIONS"
        '
        'btn2
        '
        Me.btn2.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btn2.Appearance.Options.UseFont = True
        Me.btn2.Location = New System.Drawing.Point(9, 28)
        Me.btn2.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.btn2.Name = "btn2"
        Me.btn2.Size = New System.Drawing.Size(131, 39)
        Me.btn2.TabIndex = 4
        Me.btn2.Text = "Delete"
        '
        'btn1
        '
        Me.btn1.Appearance.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.btn1.Appearance.Options.UseFont = True
        Me.btn1.Location = New System.Drawing.Point(146, 28)
        Me.btn1.Margin = New System.Windows.Forms.Padding(3, 4, 3, 4)
        Me.btn1.Name = "btn1"
        Me.btn1.Size = New System.Drawing.Size(131, 39)
        Me.btn1.TabIndex = 3
        Me.btn1.Text = "Register"
        '
        'txtServiceDesc
        '
        Me.txtServiceDesc.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtServiceDesc.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtServiceDesc.Location = New System.Drawing.Point(18, 131)
        Me.txtServiceDesc.Margin = New System.Windows.Forms.Padding(4)
        Me.txtServiceDesc.Name = "txtServiceDesc"
        Me.txtServiceDesc.Size = New System.Drawing.Size(442, 34)
        Me.txtServiceDesc.TabIndex = 3
        '
        'Label28
        '
        Me.Label28.AutoSize = True
        Me.Label28.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label28.Location = New System.Drawing.Point(13, 99)
        Me.Label28.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label28.Name = "Label28"
        Me.Label28.Size = New System.Drawing.Size(213, 28)
        Me.Label28.TabIndex = 26
        Me.Label28.Text = "SERVICE DESCRIPTION:"
        '
        'Label27
        '
        Me.Label27.AutoSize = True
        Me.Label27.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label27.Location = New System.Drawing.Point(13, 61)
        Me.Label27.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label27.Name = "Label27"
        Me.Label27.Size = New System.Drawing.Size(135, 28)
        Me.Label27.TabIndex = 25
        Me.Label27.Text = "SERVICE TYPE:"
        '
        'Label26
        '
        Me.Label26.AutoSize = True
        Me.Label26.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label26.Location = New System.Drawing.Point(13, 22)
        Me.Label26.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label26.Name = "Label26"
        Me.Label26.Size = New System.Drawing.Size(144, 28)
        Me.Label26.TabIndex = 24
        Me.Label26.Text = "SERVICE CODE:"
        '
        'txtServiceCode
        '
        Me.txtServiceCode.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtServiceCode.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtServiceCode.Location = New System.Drawing.Point(231, 22)
        Me.txtServiceCode.Margin = New System.Windows.Forms.Padding(4)
        Me.txtServiceCode.Mask = "#####"
        Me.txtServiceCode.Name = "txtServiceCode"
        Me.txtServiceCode.Size = New System.Drawing.Size(78, 27)
        Me.txtServiceCode.TabIndex = 1
        '
        'cbServiceType
        '
        Me.cbServiceType.DropDownStyle = System.Windows.Forms.ComboBoxStyle.DropDownList
        Me.cbServiceType.FlatStyle = System.Windows.Forms.FlatStyle.System
        Me.cbServiceType.Font = New System.Drawing.Font("Segoe UI", 12.0!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.cbServiceType.FormattingEnabled = True
        Me.cbServiceType.Items.AddRange(New Object() {"ARRASTRE", "STEVEDORING", "OTHER"})
        Me.cbServiceType.Location = New System.Drawing.Point(231, 53)
        Me.cbServiceType.Margin = New System.Windows.Forms.Padding(4)
        Me.cbServiceType.Name = "cbServiceType"
        Me.cbServiceType.Size = New System.Drawing.Size(221, 36)
        Me.cbServiceType.TabIndex = 2
        '
        'frmservice
        '
        Me.Appearance.BackColor = System.Drawing.Color.White
        Me.Appearance.Options.UseBackColor = True
        Me.Appearance.Options.UseFont = True
        Me.AutoScaleDimensions = New System.Drawing.SizeF(7.0!, 17.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.ClientSize = New System.Drawing.Size(692, 573)
        Me.Controls.Add(Me.GroupBox18)
        Me.Font = New System.Drawing.Font("Segoe UI", 7.8!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Name = "frmservice"
        Me.Text = "SERVICE FORM"
        Me.GroupBox18.ResumeLayout(False)
        Me.GroupBox18.PerformLayout()
        Me.GroupBox3.ResumeLayout(False)
        Me.ResumeLayout(False)

    End Sub
    Friend WithEvents GroupBox18 As System.Windows.Forms.GroupBox
    Friend WithEvents txtServiceDesc As System.Windows.Forms.TextBox
    Friend WithEvents Label28 As System.Windows.Forms.Label
    Friend WithEvents Label27 As System.Windows.Forms.Label
    Friend WithEvents Label26 As System.Windows.Forms.Label
    Friend WithEvents txtServiceCode As System.Windows.Forms.MaskedTextBox
    Friend WithEvents cbServiceType As System.Windows.Forms.ComboBox
    Friend WithEvents GroupBox3 As System.Windows.Forms.GroupBox
    Friend WithEvents btn2 As DevExpress.XtraEditors.SimpleButton
    Friend WithEvents btn1 As DevExpress.XtraEditors.SimpleButton
End Class
